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

namespace App\Tests\Services\System;

use App\Services\System\TrustedHostsChecker;
use App\Services\System\TrustedUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class TrustedUrlGeneratorTest extends TestCase
{
    private function createGenerator(UrlGeneratorInterface $urlGenerator, string $trustedHosts, string $defaultUri): TrustedUrlGenerator
    {
        return new TrustedUrlGenerator($urlGenerator, new TrustedHostsChecker($trustedHosts), $defaultUri);
    }

    public function testUsesCurrentRequestContextWhenTrustedHostsIsConfigured(): void
    {
        //If TRUSTED_HOSTS is configured, the request's host is already validated by Symfony, so it should be used as-is
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('getContext');
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('pw_reset_new_pw', ['user' => 'john', 'token' => 'abc'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://request-host.example.com/pw_reset/new_pw/john/abc');

        $generator = $this->createGenerator($urlGenerator, 'example\.com', 'https://trusted.example.com/');

        $url = $generator->generate('pw_reset_new_pw', ['user' => 'john', 'token' => 'abc']);
        $this->assertSame('https://request-host.example.com/pw_reset/new_pw/john/abc', $url);
    }

    public function testForcesDefaultUriHostWhenTrustedHostsIsNotConfigured(): void
    {
        //If TRUSTED_HOSTS is not configured, the request's Host header is attacker-controllable, so the
        //host/scheme/base path configured via DEFAULT_URI must be used instead.
        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('attacker.evil');
        $context->setBaseUrl('');

        $capturedHost = null;
        $capturedScheme = null;

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($context);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('pw_reset_new_pw', ['user' => 'john', 'token' => 'abc'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturnCallback(function () use ($context, &$capturedHost, &$capturedScheme) {
                //Capture the context state as it is seen by the URL generator during the call
                $capturedHost = $context->getHost();
                $capturedScheme = $context->getScheme();

                return $capturedScheme.'://'.$capturedHost.'/pw_reset/new_pw/john/abc';
            });

        $generator = $this->createGenerator($urlGenerator, '', 'https://trusted.example.com/');

        $url = $generator->generate('pw_reset_new_pw', ['user' => 'john', 'token' => 'abc']);

        $this->assertSame('trusted.example.com', $capturedHost);
        $this->assertSame('https', $capturedScheme);
        $this->assertSame('https://trusted.example.com/pw_reset/new_pw/john/abc', $url);
    }

    public function testRestoresOriginalContextAfterGenerating(): void
    {
        //The context is shared with the rest of the application, so it must not be left modified afterward
        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('attacker.evil');
        $context->setHttpPort(8080);
        $context->setBaseUrl('/original');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($context);
        $urlGenerator->method('generate')->willReturn('https://trusted.example.com/foo');

        $generator = $this->createGenerator($urlGenerator, '', 'https://trusted.example.com/sub/');
        $generator->generate('some_route');

        $this->assertSame('attacker.evil', $context->getHost());
        $this->assertSame('http', $context->getScheme());
        $this->assertSame(8080, $context->getHttpPort());
        $this->assertSame('/original', $context->getBaseUrl());
    }

    public function testRestoresOriginalContextEvenWhenGenerateThrows(): void
    {
        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('attacker.evil');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($context);
        $urlGenerator->method('generate')->willThrowException(new \RuntimeException('route not found'));

        $generator = $this->createGenerator($urlGenerator, '', 'https://trusted.example.com/');

        try {
            $generator->generate('unknown_route');
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException) {
            //Expected
        }

        $this->assertSame('attacker.evil', $context->getHost());
        $this->assertSame('http', $context->getScheme());
    }

    public function testUsesHttpsPortForHttpsDefaultUri(): void
    {
        $context = new RequestContext();

        $capturedHttpsPort = null;

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($context);
        $urlGenerator->method('generate')->willReturnCallback(function () use ($context, &$capturedHttpsPort) {
            $capturedHttpsPort = $context->getHttpsPort();

            return 'https://trusted.example.com:8443/foo';
        });

        $generator = $this->createGenerator($urlGenerator, '', 'https://trusted.example.com:8443/');
        $generator->generate('some_route');

        $this->assertSame(8443, $capturedHttpsPort);
    }

    public function testUsesBasePathFromDefaultUri(): void
    {
        $context = new RequestContext();

        $capturedBaseUrl = null;

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn($context);
        $urlGenerator->method('generate')->willReturnCallback(function () use ($context, &$capturedBaseUrl) {
            $capturedBaseUrl = $context->getBaseUrl();

            return 'https://trusted.example.com/partdb/foo';
        });

        $generator = $this->createGenerator($urlGenerator, '', 'https://trusted.example.com/partdb/');
        $generator->generate('some_route');

        $this->assertSame('/partdb', $capturedBaseUrl);
    }
}
