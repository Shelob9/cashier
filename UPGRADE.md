# Upgrade Guide

## Upgrading To 10.0 From 9.0

Cashier v10 is a major new release aimed to provide support for the new Stripe API's which will help you cover the new SCA regulations in Europe that will start in September 2019. If you have a business in the EU, we recommend you to give [Stripe's guide on PSD2 and SCA](https://stripe.com/en-be/guides/strong-customer-authentication) a read as well as [their docs on the new SCA API's](https://stripe.com/docs/strong-customer-authentication).

Needless to say, there are quite a few breaking changes in Cashier to accommodate for these new API's. Almost entire Cashier got a thorough overhaul on the inside. In this upgrade guide we'll try to cover as much as possible. Give this guide a thorough read before upgrading to v10. We also recommend to give the related prs mentioned below a look.

> With so many changes we can't possible begin to cover every bit and piece. If you want to see everything that's changed have a look at the code diff between the 9.0 branch and the v10 release: https://github.com/laravel/cashier/compare/9.0...v10.0.0

### Minimum Laravel Version

Commit: https://github.com/laravel/cashier/commit/66beefe533430ce27f4eff342fc36c43ee43072c

The minimum Laravel version in Cashier v10 is now v5.8.

### Fixed API Version

PR: https://github.com/laravel/cashier/pull/643

One of the most important changes in Cashier v10 is that from now on, the Stripe API version is fixed from Cashier. This is because in the past, people would upgrade to the latest Stripe API versions but Cashier might not have been updated yet for it. By controlling the API version ourselves, we can prevent bugs more easily and make updates to newer API versions more easily.

### Publishable Key

PR: https://github.com/laravel/cashier/pull/653

The `STRIPE_KEY` is now always the publishable key and the `STRIPE_SECRET` the secret key. Before this, there was some confusion about these keys being used with the same naming internally. Now they both have their correctly named settings in the config file.

### Payment Intents

PR: https://github.com/laravel/cashier/pull/667

These changes bring support for the new Payment Intents API to charges and subscriptions. As there are quite some breaking changes here let's go over the most prominent ones below.

#### Exception Throwing

Any payment action will now throw an exception when a payment either fails or when the payment requires a secondary action in order to be completed. This goes for single charges, invoicing customers directly, subscribing to a new plan or swapping plans. You can catch these exceptions and decide for themselves how to handle these by either letting Stripe handle everything for them (to be set up in the Stripe dashboard) or use the custom built-in solution which will be added in the next commit.

An example of how to implement this could be:

```php
use Laravel\Cashier\Exceptions\IncompletePayment;

try {
    $subscription = $user->newSubscription('default', $planId)->create($token);
} catch (IncompletePayment $exception) {
    return redirect()->route('cashier.payment', [$exception->payment->id(), 'redirect' => route('home')]);
}
```

The above `IncompletePayment` exception could be an instance of a `PaymentFailure` when a card failure occured or of a `PaymentActionRequired` when a secondary action is needed to complete the payment. In the above example, the user is redirected to a new dedicated payment page which ships with Cashier. Here, the user can provide its payment details again and fulfill the secondary action (like 3D Secure). After that they're redirected to the url you've provided in the `redirect` parameter.

Exceptions can be thrown for the following methods: `charge`, `invoiceFor`, `invoice` on the `Billable` user, on the `create` method when creating a new subscription or on the `swap` method on a subscription when swapping subscription. Technically, on every of these methods such an exception can occur and you should handle this gracefully in your app. The provided payment page by Cashier offers an easy transition to the new SCA requirements but you may also choose to catch the exceptions and then let Stripe handle the rest through its settings (by emailing your customers).

It should also be noted that previously, the `create` method on the subscription builder would immediately cancel any subscription with an `incomplete` or `incomplete_expired` status and throw a `SubscriptionCreationFailed` exception. This has now been replaced with the behavior described above and the `SubscriptionCreationFailed` has been removed.

#### New `status` Column

A new status column is introduced for subscriptions as well. Whenever an attempt is made to subscribe to a plan but a secondary payment action is required, the subscription will be put into a state of "incomplete" while payment confirmation is awaited. A new `incomplete` method on the `Subscription` object has been added to check for an incomplete status. As soon as payment has been properly processed, a webhook will update the subscription's status to active. You can add this column with the migration below:

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->string('status');
});
```

#### Confirmation Email

These changes add a built-in way for Cashier to send reminder emails to the customer when off-session payment confirmation is needed, for example, when a subscription renews. It can be enabled by setting an env variable `CASHIER_PAYMENT_EMAILS=true`. By default these emails are disabled. Note that you'll need to setup a working email driver for this.

One limitation of this addition is that emails will be sent out even when customers are on-session during a payment that requires an extra action. This is because there's no way to know for Stripe that the payment was done on- or off-session. But a customer will never be charged twice and will simply see a "Payment Successful" message if they visit the payment page again.

### Single Charges

These notes involve changes made to the `charge` method on the `Billable` trait.

#### Payment Method

The `charge` method now accepts a payment method instead of a token. You will need to update your JS integration to retrieve a payment method id instead of a source token. These changes were done because this is now the recommended way by Stripe to work with payments. [More info about that can be found here](https://stripe.com/docs/payments/payment-methods#transitioning). 

In an upcoming update all card methods as well as the `create` method on the subscription builder will be updated as well.

#### Stripe Customer

PR: https://github.com/laravel/cashier/pull/683

Another minor change is that if a payment method was provided but the billable user is already a Stripe customer, then that customer ID will **always** be passed to Stripe to make sure the payment is associated with the customer you're performing the payment with. Before this when providing a payment source, the customer id on the billable user was ignored and the payment wasn't associated with the customer at all. We considered this to be a bug.

### Webhooks

#### Webhooks Are Now Required

With the latest updates in v10 and the way Stripe has shifted to an asynchronous workflow with payment intents, webhooks are now an essential part of this workflow and need to be enabled in order for your app to properly update subscriptions when handling payments. [You can read more about enabling webhooks here](https://laravel.com/docs/billing#handling-stripe-webhooks).

#### Webhooks Are Now Auto-loaded

PR: https://github.com/laravel/cashier/pull/672

The webhooks route is now also automatically loaded for you and doesn't needs to be added manually to your routes file anymore.

### Migrations

PR: https://github.com/laravel/cashier/pull/663

Just like in other Laravel packages, Cashier's migrations now ship with its package. They're automatically registered and will be executed when you run `php artisan migrate`. If you already have these migrations run you would want to disable this by adding `Cashier::ignoreMigrations();` to the `boot` method in your `AppServiceProvider`.

### Config File

PR: https://github.com/laravel/cashier/pull/690

Cashier now ships with a dedicated config file like many of the other Laravel packages. This means that previous settings from the `services.php` file are transferred to the new `cashier` config file. Many methods from the `Cashier` class have been transferred as settings to the config file.

The `STRIPE_MODEL` env variable has been renamed to `CASHIER_MODEL`.

### Invoices

PR: https://github.com/laravel/cashier/pull/685

Internals in Cashier on how to properly format money values have been refactored. Cashier now makes use of the `moneyphp/money` library to format these values. Because of this refactor, the `useCurrencySymbol`, `usesCurrencySymbol`, `guessCurrencySymbol` methods, and the `$symbol` parameter on the `useCurrency` have been removed.

The starting balance is now no longer subtracted from the subtotal of an invoice. 

All `raw` methods on the `Invoice` object now return integers instead of floats. These integers represent money values starting from cents.

The invoice PDF also got a new layout. See an example in the above pull request.

### Subscriptions

#### Swap Options

PR: https://github.com/laravel/cashier/pull/620

The `swap` method now accepts a new `$options` argument to easily set extra options on the subscription.

### Customers

PR: https://github.com/laravel/cashier/pull/682

The following methods now require that the `Billable` user is a customer registered with Stripe before they can be called: `tab`, `invoice`, `upcomingInvoice`, `invoices`, `cards`, `updateCard`, `applyCoupon`.

### Carbon

PR Carbon v1: https://github.com/laravel/cashier/pull/694
PR Carbon v2: https://github.com/laravel/cashier/pull/607

Support for Carbon v1 was dropped because it's not supported anymore. Support for Carbon v2 was added.


## Upgrading To 9.3 From 9.2

### Custom Subscription Creation Exception

[In their 2019-03-14 API update](https://stripe.com/docs/upgrades#2019-03-14), Stripe changed the way they handle new subscriptions when card payment fails. Instead of letting the creation of the subscription fail, the subscription is failed with an "incomplete" status. Because of this a Cashier customer will always get a successful subscription. Previously a card exception was thrown.

To accommodate for this new behavior from now on Cashier will cancel that subscription immediately and throw a custom `SubscriptionCreationFailed` exception when a subscription is created with an "incomplete" or "incomplete_expired" status. We've decided to do this because in general you want to let a customer only start using your product when payment was received.

If you were relying on catching the `\Stripe\Error\Card` exception before you should now rely on catching the `Laravel\Cashier\Exceptions\SubscriptionCreationFailed` exception instead. 

### Card Failure When Swapping Plans

Previously, when a user attempted to change subscription plans and their payment failed, the resulting exception bubbled up to the end user and the update to the subscription in the application was not performed. However, the subscription was still updated in Stripe itself resulting in the application and Stripe becoming out of sync.

However, Cashier will now catch the payment failure exception while allowing the plan swap to continue. The payment failure will be handled by Stripe and Stripe may attempt to retry the payment at a later time. If the payment fails during the final retry attempt, Stripe will execute the action you have configured in your billing settings: https://stripe.com/docs/billing/lifecycle#settings

Therefore, you should ensure you have configured Cashier to handle Stripe's webhooks. When configured properly, this will allow Cashier to mark the subscription as cancelled when the final payment retry attempt fails and Stripe notifies your application via a webhook request. Please refer to our [instructions for setting up Stripe webhooks with Cashier.](https://laravel.com/docs/master/billing#handling-stripe-webhooks).


## Upgrading To 9.0 From 8.0

### PHP & Laravel Version Requirements

Like the latest releases of the Laravel framework, Laravel Cashier now requires PHP >= 7.1.3. We encourage you to upgrade to the latest versions of PHP and Laravel before upgrading to Cashier 9.0.

### The `createAsStripeCustomer` Method

The `updateCard` call was extracted from the `createAsStripeCustomer` method on the `Billable` trait in PR [#588](https://github.com/laravel/cashier/pull/588). In addition, the `$token` parameter was removed.

If you were calling the `createAsStripeCustomer` method directly you now should call the `updateCard` method separately after calling the `createAsStripeCustomer` method. This provides the opportunity for more granularity when handling errors for the two calls.

### WebhookController Changes

Instead of calling the Stripe API to verify incoming webhook events, Cashier now only uses webhook signatures to verify that events it receives are authentic as of [PR #591](https://github.com/laravel/cashier/pull/591).

The `VerifyWebhookSignature` middleware is now automatically added to the `WebhookController` if the `services.stripe.webhook.secret` value is set in your `services.php` configuration file. By default, this configuration value uses the `STRIPE_WEBHOOK_SECRET` environment variable.

If you manually added the `VerifyWebhookSignature` middleware to your Cashier webhook route, you may remove it since it will now be added automatically.

If you were using the `CASHIER_ENV` environment variable to test incoming webhooks, you should set the `STRIPE_WEBHOOK_SECRET` environment variable to `null` to achieve the same behavior.

More information about verifying webhooks can be found [in the Cashier documentation](https://laravel.com/docs/5.7/billing#verifying-webhook-signatures).
