<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group("slow")]
#[Group("DB")]
final class LabelControllerTest extends WebTestCase
{
    private const PRINT_BUTTON = '[data-controller="pages--print-agent-print"]';

    private function loginAsAdmin($client): void
    {
        $user = $client->getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $client->loginUser($user);
    }

    public function testDialogWithProfileHasPrintButton(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(self::PRINT_BUTTON
            .'[data-pages--print-agent-print-profile-value="1"]'
            .'[data-pages--print-agent-print-ids-value="1"]'
            .'[data-pages--print-agent-print-type-value="part"]');
    }

    public function testDialogWithoutProfileHasNoPrintButton(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/label/dialog?target_id=1&target_type=part');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists(self::PRINT_BUTTON);
    }

    public function testPartPageLabelMenuHasPrintIcons(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/part/1');

        $this->assertResponseIsSuccessful();
        //Fixture "Profile 1" (id 1) is a part profile shown in the dropdown
        $this->assertSelectorExists('.dropdown-menu '.self::PRINT_BUTTON
            .'[data-pages--print-agent-print-profile-value="1"]'
            .'[data-pages--print-agent-print-ids-value="1"]'
            .'[data-pages--print-agent-print-type-value="part"]');
    }

    private const AGENT_FORM = 'form[action="/en/label/print"]';

    /**
     * Creates an agent of the given user that reports one printer "fake", and makes it the default printer of profile 1.
     */
    private function agentWithDefaultPrinter(KernelBrowser $client, string $owner = 'admin', string $lastSeen = 'now'): Agent
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $agent = (new Agent())->setOwner($em->getRepository(User::class)->findOneBy(['name' => $owner]))
            ->setUuid(bin2hex(random_bytes(8)))->setName('bench pc')
            ->setPrinters([['id' => 'fake', 'name' => 'Fake QL']])
            ->setLastSeenAt(new \DateTimeImmutable($lastSeen));
        $em->persist($agent);
        $em->find(LabelProfile::class, 1)->setDefaultPrinter('fake');
        $em->flush();
        return $agent;
    }

    /** @return list<AgentJob> */
    private function jobs(KernelBrowser $client): array
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        return $em->getRepository(AgentJob::class)->findAll();
    }

    public function testDialogOffersAgentPrinter(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $agent = $this->agentWithDefaultPrinter($client);
        $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(self::AGENT_FORM.' button[name="printer"][value="'.$agent->getId().'|fake"]');
    }

    public function testPrintFromDialogCreatesJob(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $agent = $this->agentWithDefaultPrinter($client);
        $crawler = $client->request('GET', '/en/label/1/dialog?target_id=1,%202;;3&target_type=part&generate=1');
        $client->submit($crawler->filter(self::AGENT_FORM.' button[name="printer"]')->first()->form());

        $this->assertResponseRedirects();
        $jobs = $this->jobs($client);
        $this->assertCount(1, $jobs);
        $this->assertSame($agent->getId(), $jobs[0]->getAgent()->getId());
        $this->assertSame('fake', $jobs[0]->getPrinter());
        $this->assertSame(AgentJobStatus::PENDING, $jobs[0]->getStatus());
        $this->assertSame(['profile_id' => 1, 'element_type' => 'part', 'element_ids' => '1,2,3'], $jobs[0]->getPayload());
        $client->followRedirect();
        $this->assertStringContainsString('Sent 3 label(s) to Fake QL', $client->getResponse()->getContent());
    }

    public function testPrintToOfflineAgentSaysSo(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->agentWithDefaultPrinter($client, lastSeen: '-1 hour');
        $crawler = $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');
        $client->submit($crawler->filter(self::AGENT_FORM.' button[name="printer"]')->first()->form());
        $client->followRedirect();

        $this->assertCount(1, $this->jobs($client), 'the job waits for the agent');
        $this->assertStringContainsString('is offline', $client->getResponse()->getContent());
    }

    public function testPrintToAnotherUsersAgentIsRejected(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $own = $this->agentWithDefaultPrinter($client);
        $foreign = $this->agentWithDefaultPrinter($client, 'user');
        $crawler = $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');
        $form = $crawler->filter(self::AGENT_FORM.' button[name="printer"][value="'.$own->getId().'|fake"]')->form();
        $this->assertSelectorNotExists(self::AGENT_FORM.' button[value="'.$foreign->getId().'|fake"]', "other users' printers are not offered");

        $client->request('POST', $form->getUri(), ['printer' => $foreign->getId().'|fake'] + $form->getValues());
        $this->assertResponseStatusCodeSame(404);
        $client->request('POST', $form->getUri(), ['_token' => 'invalid'] + $form->getValues());
        $this->assertResponseStatusCodeSame(400);
        $this->assertCount(0, $this->jobs($client));
    }

    public function testWithoutAgentPrinterTheDialogUsesTheLocalAgent(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/en/label/1/dialog?target_id=1&target_type=part&generate=1');

        $this->assertSelectorNotExists(self::AGENT_FORM);
        $this->assertSelectorExists(self::PRINT_BUTTON);
    }

    public function testLabelMenuSendsToTheDefaultPrinter(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $agent = $this->agentWithDefaultPrinter($client);
        $crawler = $client->request('GET', '/en/part/1');

        $this->assertSelectorExists('.dropdown-menu '.self::AGENT_FORM.' button[name="printer"][value="'.$agent->getId().'|fake"]');
        $client->submit($crawler->filter('.dropdown-menu '.self::AGENT_FORM.' button[name="printer"]')->first()->form());
        $this->assertResponseRedirects('/en/part/1');
        $this->assertSame('1', $this->jobs($client)[0]->getPayload()['element_ids']);
    }

    private function jobFor(KernelBrowser $client, Agent $agent): AgentJob
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $job = (new AgentJob())->setAgent($agent)->setPrinter('fake')
            ->setPayload(['profile_id' => 1, 'element_type' => 'part', 'element_ids' => '7-9']);
        $em->persist($job);
        $em->flush();
        return $job;
    }

    public function testAgentPageListsOwnAgentsAndJobs(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $own = $this->agentWithDefaultPrinter($client);
        $this->jobFor($client, $own);
        $foreign = $this->agentWithDefaultPrinter($client, 'user')->setName('someone else');
        $client->getContainer()->get(EntityManagerInterface::class)->flush();
        $client->request('GET', '/en/label/agent');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('bench pc', $content);
        $this->assertStringContainsString('Fake QL', $content);
        $this->assertStringContainsString('7-9', $content);
        $this->assertStringNotContainsString('someone else', $content);
    }

    public function testAgentPageActions(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $agent = $this->agentWithDefaultPrinter($client);
        $job = $this->jobFor($client, $agent);
        $jobId = $job->getId();
        $crawler = $client->request('GET', '/en/label/agent');

        $client->submit($crawler->filter('form[action$="/jobs/'.$jobId.'/resend"]')->form());
        $this->assertResponseRedirects('/en/label/agent');
        $this->assertCount(2, $this->jobs($client));

        $crawler = $client->request('GET', '/en/label/agent');
        $client->submit($crawler->filter('form[action$="/jobs/'.$jobId.'/cancel"]')->form());
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $this->assertSame(AgentJobStatus::CANCELLED, $em->find(AgentJob::class, $jobId)->getStatus());

        $crawler = $client->request('GET', '/en/label/agent');
        $client->submit($crawler->filter('form[action$="/agents/'.$agent->getId().'/remove"]')->form());
        $this->assertCount(0, $this->jobs($client), 'removing an agent removes its jobs');
        $this->assertNull($em->find(Agent::class, $agent->getId()));
    }

    public function testAgentPageActionsOnForeignAgentsAre404(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->agentWithDefaultPrinter($client); //an own agent, so the page has a form token
        $foreign = $this->agentWithDefaultPrinter($client, 'user');
        $job = $this->jobFor($client, $foreign);
        $crawler = $client->request('GET', '/en/label/agent');
        $token = $crawler->filter('input[name="_token"][data-agent-token]')->attr('value');

        foreach (['/en/label/agent/jobs/'.$job->getId().'/resend', '/en/label/agent/jobs/'.$job->getId().'/cancel',
                     '/en/label/agent/agents/'.$foreign->getId().'/remove'] as $url) {
            $client->request('POST', $url, ['_token' => $token]);
            $this->assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testProfileFormSuggestsAgentPrinters(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->agentWithDefaultPrinter($client);
        $client->request('GET', '/en/label_profile/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name$="[default_printer]"][list="agent_printers"][value="fake"]');
        $this->assertSelectorExists('datalist#agent_printers option[value="fake"]');
    }
}
