<?php

namespace Laravel\Cashier\Tests\Integration;

use Stripe\Card;
use Stripe\PaymentMethod;

class CardsTest extends IntegrationTestCase
{
    public function test_we_can_set_the_default_card()
    {
        $user = $this->createCustomer('we_can_set_the_default_card');
        $user->createAsStripeCustomer();
        $user->updateCard('pm_card_visa');

        $card = $user->defaultCard();

        $this->assertInstanceOf(PaymentMethod::class, $card);
        $this->assertInstanceOf(Card::class, $card->card);
    }
}
