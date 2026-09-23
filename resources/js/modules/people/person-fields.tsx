import type { FormDataConvertible } from '@inertiajs/core';
import TagInput from '@/components/core/tag-input';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';
import { suggest } from '@/routes/people/areas';
import type { PersonDetails } from './types';

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const suggestAreas = (q: string) => suggest.url({ query: { q } });

export function personFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        areas: data.areas ?? [],
        remove_photo: data.remove_photo === 'on',
    };
}

export default function PersonFields({
    errors,
    defaults,
}: {
    errors: Partial<Record<string, string>>;
    defaults?: PersonDetails;
}) {
    const { t } = useTranslation();

    const text = (
        name: keyof PersonDetails,
        label: string,
        type = 'text',
        placeholder?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{t(label)}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={(defaults?.[name] as string | null) ?? ''}
                placeholder={placeholder ? t(placeholder) : undefined}
                required={name === 'name'}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                {text('name', 'Name')}
                {text(
                    'academic_title',
                    'Academic title',
                    'text',
                    'e.g. Associate Professor',
                )}
            </div>
            {text(
                'affiliation',
                'Affiliation',
                'text',
                'Faculty, research centre…',
            )}

            <div className="grid gap-2">
                <Label htmlFor="areas">{t('Expertise areas')}</Label>
                <TagInput
                    id="areas"
                    name="areas[]"
                    defaultValue={defaults?.areas}
                    suggestUrl={suggestAreas}
                    placeholder="Add an area and press Enter"
                />
                <InputError message={errors.areas} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="short_bio">{t('Short bio')}</Label>
                <textarea
                    id="short_bio"
                    name="short_bio"
                    rows={2}
                    maxLength={1000}
                    defaultValue={defaults?.short_bio ?? ''}
                    className={textareaClass}
                    placeholder={t(
                        'One or two sentences, ready to send to a journalist.',
                    )}
                />
                <InputError message={errors.short_bio} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="bio">{t('Bio')}</Label>
                <textarea
                    id="bio"
                    name="bio"
                    rows={5}
                    defaultValue={defaults?.bio ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.bio} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="keywords">{t('Keywords')}</Label>
                <textarea
                    id="keywords"
                    name="keywords"
                    rows={2}
                    defaultValue={defaults?.keywords ?? ''}
                    className={textareaClass}
                    placeholder={t(
                        'Topics this person can talk about, to help searching.',
                    )}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                {text('email', 'Email', 'email')}
                {text('phone', 'Phone')}
                {text(
                    'languages',
                    'Languages',
                    'text',
                    'e.g. Portuguese, English',
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="media_notes">
                    {t('Media experience and availability')}
                </Label>
                <textarea
                    id="media_notes"
                    name="media_notes"
                    rows={2}
                    defaultValue={defaults?.media_notes ?? ''}
                    className={textareaClass}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                {text('consent_at', 'Consent', 'date')}
                {text('last_reviewed_at', 'Last reviewed', 'date')}
                <div className="grid gap-2">
                    <Label htmlFor="photo">{t('Photo')}</Label>
                    <Input
                        id="photo"
                        name="photo"
                        type="file"
                        accept="image/*"
                    />
                    <InputError message={errors.photo} />
                    {defaults?.photo_url && (
                        <label className="flex items-center gap-2 text-xs">
                            <Checkbox name="remove_photo" /> {t('Remove photo')}
                        </label>
                    )}
                </div>
            </div>
            <p className="-mt-2 text-xs text-muted-foreground">
                {t(
                    'Consent: date the person agreed to be suggested to the media. Bios not reviewed for a year are flagged.',
                )}
            </p>
        </div>
    );
}
