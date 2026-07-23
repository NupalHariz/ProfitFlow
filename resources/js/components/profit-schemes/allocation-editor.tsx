import { Form } from '@inertiajs/react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { percentageTotal } from '@/lib/percentage';
import type { ProfitRecipientLookup, ProfitSchemeAllocation } from '@/types';

type AllocationRow = {
    key: string;
    profit_recipient_id: string;
    percentage: string;
};

type Props = {
    action: string;
    method: 'post';
    allocations: ProfitSchemeAllocation[];
    recipients: ProfitRecipientLookup[];
};

let rowSequence = 0;

function nextRowKey(): string {
    rowSequence += 1;

    return `allocation-${rowSequence}`;
}

export default function AllocationEditor({
    action,
    method,
    allocations,
    recipients,
}: Props) {
    const [rows, setRows] = useState<AllocationRow[]>(() =>
        allocations.length > 0
            ? allocations.map((allocation) => ({
                  key: `allocation-${allocation.id}`,
                  profit_recipient_id:
                      allocation.profit_recipient_id.toString(),
                  percentage: allocation.percentage,
              }))
            : [
                  {
                      key: nextRowKey(),
                      profit_recipient_id: '',
                      percentage: '',
                  },
              ],
    );
    const total = useMemo(
        () => percentageTotal(rows.map((row) => row.percentage)),
        [rows],
    );
    const selectedRecipientIds = rows
        .map((row) => row.profit_recipient_id)
        .filter(Boolean);
    const hasInactiveSelection = rows.some((row) => {
        const recipient = recipients.find(
            (candidate) => candidate.id.toString() === row.profit_recipient_id,
        );

        return recipient !== undefined && !recipient.is_active;
    });

    function updateRow(
        key: string,
        changes: Partial<Omit<AllocationRow, 'key'>>,
    ): void {
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, ...changes } : row,
            ),
        );
    }

    function addRow(): void {
        setRows((current) => [
            ...current,
            {
                key: nextRowKey(),
                profit_recipient_id: '',
                percentage: '',
            },
        ]);
    }

    function removeRow(key: string): void {
        setRows((current) => current.filter((row) => row.key !== key));
    }

    const totalPresentation = {
        ready: {
            label: 'Ready to publish',
            className:
                'border-emerald-600/30 bg-emerald-500/10 text-emerald-700',
        },
        incomplete: {
            label: 'Incomplete',
            className: 'border-amber-600/30 bg-amber-500/10 text-amber-700',
        },
        exceeds: {
            label: 'Exceeds 100%',
            className: 'border-red-600/30 bg-red-500/10 text-red-700',
        },
        invalid: {
            label: 'Invalid input',
            className: 'border-red-600/30 bg-red-500/10 text-red-700',
        },
    }[total.state];

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            className="space-y-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 p-4">
                        <div>
                            <p className="text-sm font-medium">
                                Allocation total
                            </p>
                            <p className="font-mono text-2xl font-semibold">
                                {total.formatted ?? '—'}%
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className={totalPresentation.className}
                        >
                            {totalPresentation.label}
                        </Badge>
                    </div>

                    <InputError message={errors.allocations} />
                    <InputError message={errors.version} />

                    {hasInactiveSelection && (
                        <p className="rounded-md border border-amber-600/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                            An existing allocation uses an inactive recipient.
                            Replace that recipient before saving.
                        </p>
                    )}

                    <div className="space-y-3">
                        {rows.map((row, index) => (
                            <div
                                key={row.key}
                                className="grid gap-3 rounded-lg border p-4 md:grid-cols-[auto_minmax(0,1fr)_minmax(10rem,0.35fr)_auto] md:items-end"
                            >
                                <GripVertical className="hidden size-5 self-center text-muted-foreground md:block" />
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`allocation-recipient-${row.key}`}
                                    >
                                        Recipient {index + 1}
                                    </Label>
                                    <input
                                        type="hidden"
                                        name={`allocations[${index}][profit_recipient_id]`}
                                        value={row.profit_recipient_id}
                                    />
                                    <Select
                                        value={
                                            row.profit_recipient_id || undefined
                                        }
                                        onValueChange={(value) =>
                                            updateRow(row.key, {
                                                profit_recipient_id: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`allocation-recipient-${row.key}`}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a recipient" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {recipients.map((recipient) => {
                                                const recipientId =
                                                    recipient.id.toString();
                                                const selectedElsewhere =
                                                    selectedRecipientIds.includes(
                                                        recipientId,
                                                    ) &&
                                                    row.profit_recipient_id !==
                                                        recipientId;

                                                return (
                                                    <SelectItem
                                                        key={recipient.id}
                                                        value={recipientId}
                                                        disabled={
                                                            selectedElsewhere ||
                                                            (!recipient.is_active &&
                                                                row.profit_recipient_id !==
                                                                    recipientId)
                                                        }
                                                    >
                                                        {recipient.display_name}{' '}
                                                        · {recipient.code}
                                                        {!recipient.is_active
                                                            ? ' (inactive)'
                                                            : ''}
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            errors[
                                                `allocations.${index}.profit_recipient_id`
                                            ]
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`allocation-percentage-${row.key}`}
                                    >
                                        Percentage
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id={`allocation-percentage-${row.key}`}
                                            name={`allocations[${index}][percentage]`}
                                            value={row.percentage}
                                            onChange={(event) =>
                                                updateRow(row.key, {
                                                    percentage:
                                                        event.target.value,
                                                })
                                            }
                                            inputMode="decimal"
                                            placeholder="0.0000"
                                            className="pr-8 font-mono"
                                        />
                                        <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                            %
                                        </span>
                                    </div>
                                    <InputError
                                        message={
                                            errors[
                                                `allocations.${index}.percentage`
                                            ]
                                        }
                                    />
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={() => removeRow(row.key)}
                                    aria-label={`Remove allocation ${index + 1}`}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-col justify-between gap-3 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addRow}
                        >
                            <Plus />
                            Add recipient
                        </Button>
                        <Button
                            disabled={
                                processing ||
                                rows.length === 0 ||
                                hasInactiveSelection
                            }
                        >
                            {processing ? 'Saving…' : 'Save allocations'}
                        </Button>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Draft totals may be below or above 100.0000%. The rows
                        are saved in the order shown; publication still requires
                        exactly 100.0000%.
                    </p>
                </>
            )}
        </Form>
    );
}
