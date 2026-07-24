import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import InputError from '@/components/input-error';
import ProfitRecipientStatusBadge from '@/components/profit-recipients/profit-recipient-status-badge';
import ProfitRecipientTypeBadge from '@/components/profit-recipients/profit-recipient-type-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ProfitRecipientCreateDefaults,
    ProfitRecipientData,
    ProfitRecipientInvestor,
    ProfitRecipientType,
    ProfitRecipientTypeOption,
} from '@/types';

type Props =
    | {
          mode: 'create';
          recipient?: never;
          investor?: never;
          investors: ProfitRecipientInvestor[];
          typeOptions: ProfitRecipientTypeOption[];
          defaults: ProfitRecipientCreateDefaults;
          investorOptionLimit: number;
      }
    | {
          mode: 'edit';
          recipient: ProfitRecipientData;
          investor: ProfitRecipientInvestor | null;
          investors?: never;
          typeOptions?: never;
          defaults?: never;
          investorOptionLimit?: never;
      };

export default function ProfitRecipientForm(props: Props) {
    const [type, setType] = useState<ProfitRecipientType>(
        props.mode === 'create' ? props.defaults.type : props.recipient.type,
    );
    const [investorId, setInvestorId] = useState(
        props.mode === 'create' ? String(props.defaults.investor_id ?? '') : '',
    );
    const [isActive, setIsActive] = useState(
        props.mode === 'create' ? props.defaults.is_active : false,
    );
    const form =
        props.mode === 'create'
            ? ProfitRecipientController.store.form()
            : ProfitRecipientController.update.form(props.recipient.id);
    const recipient = props.mode === 'edit' ? props.recipient : null;

    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.recipient} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="code" required>
                                Code
                            </Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={
                                    recipient?.code ??
                                    (props.mode === 'create'
                                        ? props.defaults.code
                                        : '')
                                }
                                required
                                autoFocus
                                maxLength={80}
                                placeholder="INVESTOR-ARIA"
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="display_name" required>
                                Display name
                            </Label>
                            <Input
                                id="display_name"
                                name="display_name"
                                defaultValue={
                                    recipient?.display_name ??
                                    (props.mode === 'create'
                                        ? props.defaults.display_name
                                        : '')
                                }
                                required
                                maxLength={160}
                                placeholder="Aria Capital"
                            />
                            <InputError message={errors.display_name} />
                        </div>
                    </div>

                    {props.mode === 'create' ? (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="type" required>
                                    Recipient type
                                </Label>
                                <input type="hidden" name="type" value={type} />
                                <Select
                                    value={type}
                                    onValueChange={(value) => {
                                        const nextType =
                                            value as ProfitRecipientType;
                                        setType(nextType);

                                        if (nextType === 'company') {
                                            setInvestorId('');
                                        }
                                    }}
                                >
                                    <SelectTrigger id="type" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {props.typeOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            {type === 'investor' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="investor_id" required>
                                        Linked Investor
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="investor_id"
                                        value={investorId}
                                    />
                                    <Select
                                        value={investorId}
                                        onValueChange={setInvestorId}
                                    >
                                        <SelectTrigger
                                            id="investor_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select an unlinked Investor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {props.investors.map((investor) => (
                                                <SelectItem
                                                    key={investor.id}
                                                    value={String(investor.id)}
                                                >
                                                    {investor.name} —{' '}
                                                    {investor.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Active and inactive Investors may be
                                        linked. Only unlinked profiles are
                                        listed.
                                    </p>
                                    <InputError message={errors.investor_id} />
                                </div>
                            ) : (
                                <>
                                    <input
                                        type="hidden"
                                        name="investor_id"
                                        value=""
                                    />
                                    <Alert>
                                        <AlertTitle>
                                            Company recipient
                                        </AlertTitle>
                                        <AlertDescription>
                                            Company recipients are not linked to
                                            Investor profiles.
                                        </AlertDescription>
                                    </Alert>
                                    <InputError message={errors.investor_id} />
                                </>
                            )}

                            <div className="space-y-3 rounded-md border p-4">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={isActive ? '1' : '0'}
                                />
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="is_active"
                                        checked={isActive}
                                        onCheckedChange={(checked) =>
                                            setIsActive(checked === true)
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="is_active">
                                            Active Profit Recipient
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Active recipients can be used in
                                            editable allocations.
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.is_active} />
                            </div>

                            <Alert>
                                <AlertTitle>Recipient assignment</AlertTitle>
                                <AlertDescription>
                                    Recipient type and Investor linkage cannot
                                    be changed after creation.
                                </AlertDescription>
                            </Alert>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-4 rounded-md border p-4 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Type
                                    </p>
                                    <div className="mt-2">
                                        <ProfitRecipientTypeBadge type={type} />
                                    </div>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Linked Investor
                                    </p>
                                    <p className="mt-2 text-sm font-medium">
                                        {props.investor?.name ??
                                            'Not applicable'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Status
                                    </p>
                                    <div className="mt-2">
                                        <ProfitRecipientStatusBadge
                                            active={props.recipient.is_active}
                                        />
                                    </div>
                                </div>
                            </div>

                            <Alert>
                                <AlertTitle>Recipient assignment</AlertTitle>
                                <AlertDescription>
                                    Recipient type and Investor linkage cannot
                                    be changed after creation.
                                </AlertDescription>
                            </Alert>
                        </>
                    )}

                    <div className="flex justify-end">
                        <Button disabled={processing}>
                            {processing
                                ? props.mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : props.mode === 'create'
                                  ? 'Create Profit Recipient'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
