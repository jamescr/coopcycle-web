<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Reactor\GrabLoopEats;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class GrabLoopEatsTest extends TestCase
{
    use ProphecyTrait;

    private $grabLoopEats;

    public function setUp(): void
    {
        $this->loopeat = $this->prophesize(LoopEatClient::class);

        $this->grabLoopEats = new GrabLoopEats(
            $this->loopeat->reveal()
        );
    }

    public function testWithoutRestaurant()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn(null);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithLoopEatDisabled()
    {
        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(false);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithReusablePackagingNotEnabled()
    {
        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(false);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithReusablePackagingQuantityEqualToZero()
    {
        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(0);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testGrab()
    {
        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $user = new ApiUser();

        $customer = new Customer();
        $customer->setUser($user);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(2);
        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->getCustomer()
            ->willReturn($customer);

        $this->loopeat
            ->return($user, 0)
            ->shouldBeCalled();

        $this->loopeat
            ->grab($user, $restaurant, 2)
            ->shouldBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }
}
