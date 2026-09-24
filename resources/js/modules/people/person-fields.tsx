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
        remove_cv: data.remove_cv === 'on',
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
                <Label htmlFor="keywords">{t('Topics of interest')}</Label>
                <textarea
                    id="keywords"
                    name="keywords"
                    rows={2}
                    defaultValue={defaults?.keywords ?? ''}
                    className={textareaClass}
                    placeholder={t(
                        'Topics this person can talk about, separated by commas.',
                    )}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="career">{t('Career')}</Label>
                <textarea
                    id="career"
                    name="career"
                    rows={5}
                    defaultValue={defaults?.career ?? ''}
                    className={textareaClass}
                    placeholder={t(
                        'Positions, projects, awards: one per line, most recent first.',
                    )}
                />
                <InputError message={errors.career} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="cv">{t('CV')}</Label>
                <Input
                    id="cv"
                    name="cv"
                    type="file"
                    accept=".pdf,.doc,.docx,.odt"
                />
                <InputError message={errors.cv} />
                {defaults?.cv && (
                    <label className="flex items-center gap-2 text-xs">
                        <Checkbox name="remove_cv" />{' '}
                        {t('Remove CV (:name)', {
                            name: defaults.cv.name ?? '',
                        })}
                    </label>
                )}
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

            <fieldset className="grid gap-4 rounded-lg border p-4">
                <legend className="px-1 text-sm font-bold">
                    {t('Dead or Alive')}
                </legend>
                <div className="grid gap-2">
                    <Label htmlFor="obituary">{t('Obituary')}</Label>
                    <textarea
                        id="obituary"
                        name="obituary"
                        rows={6}
                        defaultValue={defaults?.obituary ?? ''}
                        className={textareaClass}
                        placeholder={t(
                            'Prepared in advance, ready to publish when needed.',
                        )}
                    />
                    <InputError message={errors.obituary} />
                </div>
                <div className="grid max-w-56 gap-2">
                    <Label htmlFor="deceased_on">{t('Date of death')}</Label>
                    <Input
                        id="deceased_on"
                        name="deceased_on"
                        type="date"
                        defaultValue={defaults?.deceased_on ?? ''}
                    />
                    <InputError message={errors.deceased_on} />
                </div>
            </fieldset>
        </div>
    );
}
