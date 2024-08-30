<?php

require 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('sk_test_51PGKaaKVs2gnspmUWIoOHANqxMuEeDBbma1kJWNafsaekoFuaHNmB9iPRd6HQErQuFt5pzblMtwmIx5OdxLDqC0A00nbJcTpLv');

try {
    // Récupérer l'abonnement
    $subscription = \Stripe\Subscription::retrieve('sub_1PtIYrKVs2gnspmUItAoqm49');
    
    // Annuler l'abonnement
    $canceledSubscription = $subscription->cancel([
        'invoice_now' => true,
        'prorate' => true
    ]);

    echo "Subscription status after cancellation: " . $canceledSubscription->status . PHP_EOL;

} catch (\Stripe\Exception\ApiErrorException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
