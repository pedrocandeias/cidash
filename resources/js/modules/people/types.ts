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
    keywords: string | null;
    deceased_on: string | null;
};

export type PersonDetails = PersonSummary & {
    bio: string | null;
    career: string | null;
    cv: { name: string | null; url: string } | null;
    photos: { id: number; url: string }[];
    obituary: string | null;
    obituary_updated_at: string | null;
    languages: string | null;
    media_notes: string | null;
    consent_at: string | null;
    last_reviewed_at: string | null;
};

/** Topics of interest are typed as one comma-separated line. */
export function topics(person: PersonSummary): string[] {
    return (person.keywords ?? '')
        .split(/[,;\n]/)
        .map((topic) => topic.trim())
        .filter(Boolean);
}

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
    keywords: 'Topics of interest',
    career: 'Career',
    obituary: 'Obituary',
    deceased_on: 'Date of death',
    languages: 'Languages',
    email: 'Email',
    phone: 'Phone',
    media_notes: 'Media experience and availability',
    consent_at: 'Consent',
    last_reviewed_at: 'Last reviewed',
};
