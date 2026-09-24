import { X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';

type Member = { id: number; name: string };

/**
 * Several people responsible for a record. Posts `assignees[]`; forms turn a
 * missing field into [] (see assigneesTransform) so removing everyone is saved.
 */
export default function AssigneesField({
    members,
    defaultValue = [],
    id = 'assignees',
    label = 'People responsible',
    error,
}: {
    members: Member[];
    defaultValue?: number[];
    id?: string;
    label?: string;
    error?: string;
}) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<number[]>(defaultValue);
    // Changing the key resets the Select to its placeholder after each pick.
    const [pickKey, setPickKey] = useState(0);
    const available = members.filter((member) => !selected.includes(member.id));

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{t(label)}</Label>
            {selected.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {selected.map((userId) => (
                        <Badge
                            key={userId}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {members.find((member) => member.id === userId)
                                ?.name ?? userId}
                            <button
                                type="button"
                                className="rounded-xs text-muted-foreground hover:text-foreground"
                                aria-label={t('Remove :name', {
                                    name:
                                        members.find(
                                            (member) => member.id === userId,
                                        )?.name ?? '',
                                })}
                                onClick={() =>
                                    setSelected(
                                        selected.filter(
                                            (other) => other !== userId,
                                        ),
                                    )
                                }
                            >
                                <X className="size-3.5" />
                            </button>
                            <input
                                type="hidden"
                                name="assignees[]"
                                value={userId}
                            />
                        </Badge>
                    ))}
                </div>
            )}
            {available.length > 0 && (
                <Select
                    key={pickKey}
                    onValueChange={(value) => {
                        setSelected([...selected, Number(value)]);
                        setPickKey(pickKey + 1);
                    }}
                >
                    <SelectTrigger id={id}>
                        <SelectValue
                            placeholder={t(
                                selected.length === 0
                                    ? 'Nobody yet: add a person'
                                    : 'Add another person',
                            )}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {available.map((member) => (
                            <SelectItem
                                key={member.id}
                                value={String(member.id)}
                            >
                                {member.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            <InputError message={error} />
        </div>
    );
}

/**
 * For form transforms: no `assignees[]` in the form means nobody is responsible.
 */
export function assigneesTransform<T extends Record<string, unknown>>(
    data: T,
): T & { assignees: unknown } {
    return { ...data, assignees: data.assignees ?? [] };
}
