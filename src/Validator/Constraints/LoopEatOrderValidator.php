<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;
use Webmozart\Assert\Assert as WebmozartAssert;

class LoopEatOrderValidator extends ConstraintValidator
{
    private $client;

    public function __construct(
        LoopEatClient $client,
        LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of "%s"', OrderInterface::class));
        }

        if (!$object->isReusablePackagingEnabled()) {
            return;
        }

        $restaurant = $object->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if (!$restaurant->isLoopeatEnabled()) {
            $this->context->buildViolation($constraint->disabled)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();
            return;
        }

        $quantity = $object->getReusablePackagingQuantity();

        if ($quantity < 1) {
            $this->context->buildViolation($constraint->insufficientQuantity)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();
            return;
        }

        try {

            $customer = $object->getCustomer();

            WebmozartAssert::isInstanceOf($customer, CustomerInterface::class);

            if (!$customer->hasUser()) {
                return;
            }

            $currentCustomer = $this->client->currentCustomer($customer->getUser());
            $loopeatBalance = $currentCustomer['loopeatBalance'];
            $pledgeReturn = $object->getReusablePackagingPledgeReturn();
            $missing = $quantity - $loopeatBalance - $pledgeReturn;

            if ($missing > 0) {
                $this->context->buildViolation($constraint->insufficientBalance)
                    ->setParameter('%count%', $missing)
                    ->atPath('reusablePackagingEnabled')
                    ->addViolation();
            }
        } catch (RequestException $e) {

            $this->context->buildViolation($constraint->requestFailed)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();

            $this->logger->error($e->getMessage());
        }

    }
}
