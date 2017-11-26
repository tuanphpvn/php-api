<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Bridge\Symfony\Validator\EventListener;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates data.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class ValidateListener
{
    private $validator;
    private $resourceMetadataFactory;
    private $container;

    public function __construct(ValidatorInterface $validator, ResourceMetadataFactoryInterface $resourceMetadataFactory, ContainerInterface $container = null)
    {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->container = $container;
    }

    /**
     * Validates data returned by the controller if applicable.
     *
     * @param GetResponseForControllerResultEvent $event
     *
     * @throws ValidationException
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $isNotHandle = function() use ($event) {
            $request = $event->getRequest();

            return $request->isMethodSafe(false)
            || $request->isMethod(Request::METHOD_DELETE)
            || !($attributes = RequestAttributesExtractor::extractAttributes($request))
            || !$attributes['receive'];
        };
        if ($isNotHandle()) {
            return;
        }

        $request = $event->getRequest();
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $data = $event->getControllerResult();
        $resourceMetadata = $this->resourceMetadataFactory->create($attributes['resource_class']);

        if (isset($attributes['collection_operation_name'])) {
            $validationGroups = $resourceMetadata->getCollectionOperationAttribute($attributes['collection_operation_name'], 'validation_groups');
        } else {
            $validationGroups = $resourceMetadata->getItemOperationAttribute($attributes['item_operation_name'], 'validation_groups');
        }

        if (!$validationGroups) {
            // Fallback to the resource
            $validationGroups = $resourceMetadata->getAttributes()['validation_groups'] ?? null;
        }

        if (
            $this->container &&
            is_string($validationGroups) &&
            $this->container->has($validationGroups) &&
            ($service = $this->container->get($validationGroups)) &&
            is_callable($service)
        ) {
            $validationGroups = $service($data);
        } elseif (is_callable($validationGroups)) {
            $validationGroups = call_user_func_array($validationGroups, [$data]);
        }

        $violations = $this->validator->validate($data, /** $constraints */null, (array) $validationGroups);
        if (0 !== count($violations)) {
            throw new ValidationException($violations);
        }
    }
}
