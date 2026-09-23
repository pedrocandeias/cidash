export type PersonSummary = {
    id: string;
    name: string;
    academic_title: string | null;
    affiliation: string | null;
    short_bio: string | null;
    email: string | null;
    phone: string | null;
    photo_url: string | null;
    areas: string[];
    needs_review: boolean;
};

export type PersonDetails = PersonSummary & {
    bio: string | null;
    keywords: string | null;
    languages: string | null;
    media_notes: string | null;
    consent_at: string | null;
    last_reviewed_at: string | null;
};

/** Text ready to paste into an email to a journalist. */
export function profileText(person: PersonSummary): string {
    return [
        [person.name, person.academic_title].filter(Boolean).join(', '),
        person.affiliation,
        '',
        person.short_bio,
        '',
        [person.email, person.phone].filter(Boolean).join(' · '),
    ]
        .filter((line) => line !== null && line !== undefined)
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

export const personFieldLabels: Record<string, string> = {
    name: 'Name',
    academic_title: 'Academic title',
    affiliation: 'Affiliation',
    short_bio: 'Short bio',
    bio: 'Bio',
    keywords: 'Keywords',
    languages: 'Languages',
    email: 'Email',
    phone: 'Phone',
    media_notes: 'Media experience and availability',
    consent_at: 'Consent',
    last_reviewed_at: 'Last reviewed',
};
