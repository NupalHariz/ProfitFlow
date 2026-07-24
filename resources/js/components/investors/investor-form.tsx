import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InvestorFormData } from '@/types';

type Props =
    | {
          mode: 'create';
          investor?: never;
      }
    | {
          mode: 'edit';
          investor: InvestorFormData;
      };

export default function InvestorForm({ mode, investor }: Props) {
    const [isActive, setIsActive] = useState(true);
    const form =
        mode === 'create'
            ? InvestorController.store.form()
            : InvestorController.update.form(investor.id);

    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.investor} />

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={investor?.name ?? ''}
                            required
                            autoFocus
                            maxLength={160}
                            placeholder="Naufal Pratama"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={investor?.email ?? ''}
                                maxLength={255}
                                placeholder="investor@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                defaultValue={investor?.phone ?? ''}
                                maxLength={40}
                                placeholder="0812 3456 7890"
                            />
                            <InputError message={errors.phone} />
                        </div>
                    </div>

                    {mode === 'create' && (
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
                                        Active Investor
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Active Investors remain available for
                                        management.
                                    </p>
                                </div>
                            </div>
                            <InputError message={errors.is_active} />
                        </div>
                    )}

                    <Alert>
                        <AlertTitle>
                            Investor and Profit Recipient are separate
                        </AlertTitle>
                        <AlertDescription>
                            {mode === 'create'
                                ? 'Create the Profit Recipient separately if this Investor will receive an allocation.'
                                : 'Contact changes do not change the linked Profit Recipient.'}
                        </AlertDescription>
                    </Alert>

                    <div className="flex justify-end">
                        <Button disabled={processing}>
                            {processing
                                ? mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : mode === 'create'
                                  ? 'Create Investor'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
