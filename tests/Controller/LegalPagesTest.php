<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que les documents acceptés à l'inscription sont publics et accessibles.
 */
final class LegalPagesTest extends WebTestCase
{
    public function testLegalPagesAndAuthenticationLinksArePublic(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au formulaire d’inscription.');
        }

        $client = static::createClient();

        $client->request('GET', '/legal/terms');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions Générales');

        $client->request('GET', '/legal/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/legal/terms"]');
        self::assertSelectorExists('a[href="/legal/privacy"]');

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/legal/terms"]');
        self::assertSelectorExists('a[href="/legal/privacy"]');
    }
}