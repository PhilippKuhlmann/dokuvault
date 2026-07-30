<?php

use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Customer ist die Mandanten-Wurzel und hat ~50 hasMany-Relationen. Zweimal war
 * dieselbe Relation unter zwei Namen definiert (dect/dects, contactpersons/
 * contactpeople) - eine davon jeweils toter Code, was beim Lesen des Models
 * echte Verwirrung stiftet ("welche ist die richtige?").
 *
 * Dieser Test erkennt solche Dubletten strukturell, statt sie beim nächsten Mal
 * wieder per Zufall zu finden.
 */
test('Customer hat keine zwei Relationen auf dasselbe Ziel', function () {
    $customer = new Customer;

    $signatures = [];

    foreach (get_class_methods($customer) as $method) {
        $reflection = new ReflectionMethod($customer, $method);

        // Nur eigene, parameterlose, öffentliche Methoden dieses Models betrachten
        if ($reflection->getDeclaringClass()->getName() !== Customer::class
            || $reflection->getNumberOfParameters() > 0
            || ! $reflection->isPublic()
            || $reflection->isStatic()) {
            continue;
        }

        try {
            $result = $customer->{$method}();
        } catch (\Throwable) {
            continue; // keine Relation (z. B. Accessor, der Daten braucht)
        }

        if (! $result instanceof Relation) {
            continue;
        }

        // Eine Relation ist eindeutig durch Zielmodell + Fremdschlüssel + lokalen Schlüssel.
        // Zwei Methoden mit identischer Signatur sind dieselbe Relation unter zwei Namen.
        $signature = implode('|', [
            get_class($result),
            get_class($result->getRelated()),
            method_exists($result, 'getForeignKeyName') ? $result->getForeignKeyName() : '',
            method_exists($result, 'getLocalKeyName') ? $result->getLocalKeyName() : '',
        ]);

        $signatures[$signature][] = $method;
    }

    $duplicates = array_filter($signatures, fn ($methods) => count($methods) > 1);

    $report = implode("\n  ", array_map(
        fn ($sig, $methods) => implode(' / ', $methods).'  ->  '.explode('|', $sig)[1],
        array_keys($duplicates),
        $duplicates
    ));

    expect($duplicates)->toBe([],
        "Doppelte Relationen auf Customer (dieselbe Beziehung unter mehreren Namen):\n  ".$report
    );
});

test('die verbliebenen Relationen funktionieren weiterhin', function () {
    $customer = Customer::factory()->create();

    // Regression: dect() wurde zugunsten von dects() entfernt, contactpeople()
    // zugunsten von contactpersons() - beide Verbliebenen müssen nutzbar sein.
    expect($customer->dects()->count())->toBe(0);
    expect($customer->contactpersons()->count())->toBe(0);

    expect(method_exists($customer, 'dect'))->toBeFalse();
    expect(method_exists($customer, 'contactpeople'))->toBeFalse();
});
