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

namespace ApiPlatform\Core\Tests\Bridge\Symfony\Validator\EventListener;

use ApiPlatform\Core\Bridge\Symfony\Validator\EventListener\ValidationExceptionListener;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ValidationExceptionListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testNotValidationException()
    {
        $createResponseForExceptionEvent = function() {
            $eventProphecy = $this->prophesize(GetResponseForExceptionEvent::class);
            $eventProphecy->getException()->willReturn(new \Exception())->shouldBeCalled();
            $eventProphecy->setResponse()->shouldNotBeCalled();

            return $eventProphecy->reveal();
        };

        $createSerializer = function() {
            $serializerProphecy = $this->prophesize(SerializerInterface::class);
            return $serializerProphecy->reveal();
        };

        $listener = new ValidationExceptionListener($createSerializer(), ['hydra' => ['application/ld+json']]);
        $listener->onKernelException($createResponseForExceptionEvent());
    }

    public function testValidationException()
    {
        $list = new ConstraintViolationList([]);

        $createEvent = function() use ($list) {
            $eventProphecy = $this->prophesize(GetResponseForExceptionEvent::class);
            $eventProphecy->getException()->willReturn(new ValidationException($list))->shouldBeCalled();
            $eventProphecy->getRequest()->willReturn(new Request())->shouldBeCalled();
            $eventProphecy->setResponse(new Response('{"foo": "bar"}', Response::HTTP_BAD_REQUEST, [
                'Content-Type' => 'application/ld+json; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'deny',
            ]))->shouldBeCalled();

            return $eventProphecy->reveal();
        };

        $createSerializer = function() use ($list) {
            $serializerProphecy = $this->prophesize(SerializerInterface::class);
            $serializerProphecy->serialize($list, 'hydra')->willReturn('{"foo": "bar"}')->shouldBeCalled();
            return $serializerProphecy->reveal();
        };

        $listener = new ValidationExceptionListener($createSerializer(), ['hydra' => ['application/ld+json']]);
        $listener->onKernelException($createEvent());
    }
}
