<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogoutTest extends WebTestCase
{
    public function testLoggedInUserCanLogOut(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = (new User())->setEmail('logout@example.com');
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action="/logout"]')->first()->form();
        $client->submit($form);

        self::assertResponseRedirects('/');

        $client->followRedirect();
        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }
}
