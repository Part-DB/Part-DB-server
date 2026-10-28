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

/*
 * The connection to a local print agent lives only in this browser: the agent runs on this computer.
 * See docs/usage/labels.md for the handoff contract.
 */

const STORAGE_KEY = 'partdb.print_agent';

/** @returns {{url: string, key: string}|null} */
export function getAgent() {
    try {
        const agent = JSON.parse(localStorage.getItem(STORAGE_KEY));
        return agent && typeof agent.url === 'string' && typeof agent.key === 'string' ? agent : null;
    } catch {
        return null;
    }
}

export function setAgent(url, key) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({url, key}));
}

export function clearAgent() {
    localStorage.removeItem(STORAGE_KEY);
}

/**
 * Parses the connection link fragment "#url=<agent url>&key=<pairing key>".
 * @returns {{url: string, key: string}|null} null unless the url is http(s) and the key is hex
 */
export function parseConnectHash(hash) {
    const params = new URLSearchParams(hash.replace(/^#/, ''));
    const key = params.get('key') ?? '';
    let url;
    try {
        url = new URL(params.get('url') ?? '');
    } catch {
        return null;
    }
    if ((url.protocol !== 'http:' && url.protocol !== 'https:') || !/^[0-9a-fA-F]+$/.test(key)) {
        return null;
    }
    return {url: url.href.replace(/\/$/, ''), key};
}

/**
 * Builds the agent's handoff URL, signed with the pairing key (HMAC-SHA256 over
 * src, type, ids, profile, ts joined by newlines). Without WebCrypto (plain http) it stays
 * unsigned and the agent asks for a confirmation click.
 */
export async function handoffUrl(agent, {type, ids, profile}) {
    const p = {src: location.origin, type, ids, profile: String(profile), ts: String(Math.floor(Date.now() / 1000))};
    if (globalThis.crypto?.subtle) {
        const enc = new TextEncoder();
        const key = await crypto.subtle.importKey('raw', enc.encode(agent.key), {name: 'HMAC', hash: 'SHA-256'}, false, ['sign']);
        const sig = await crypto.subtle.sign('HMAC', key, enc.encode([p.src, p.type, p.ids, p.profile, p.ts].join('\n')));
        p.sig = Array.from(new Uint8Array(sig), b => b.toString(16).padStart(2, '0')).join('');
    }
    return agent.url + '/v1/handoff?' + new URLSearchParams(p);
}
