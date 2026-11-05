import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Link, previewHref } from '../../resources/js/lib/preview-navigation';

describe('isolated preview navigation', () => {
    it.each([
        ['/', '/preview'],
        ['/?role=ra', '/preview?role=ra'],
        ['/users?role=admin', '/preview/users?role=admin'],
        ['/tickets/PRD-2026-0012?tab=documents', '/preview/tickets/PRD-2026-0012?tab=documents'],
        ['/preview/audit', '/preview/audit'],
        ['https://wa.me/62812345678', 'https://wa.me/62812345678'],
        ['#documents', '#documents'],
    ])('maps %s to %s', (source, expected) => {
        expect(previewHref(source)).toBe(expected);
    });

    it('keeps demo links away from the real user management endpoint', () => {
        render(<Link href="/users?role=admin">Pengguna demo</Link>);

        expect(screen.getByRole('link', { name: 'Pengguna demo' })).toHaveAttribute('href', '/preview/users?role=admin');
    });
});
