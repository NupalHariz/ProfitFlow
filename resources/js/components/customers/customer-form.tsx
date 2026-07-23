import { Form } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CustomerFormData } from '@/types';

type Props =
    | {
          mode: 'create';
          customer?: never;
      }
    | {
          mode: 'edit';
          customer: CustomerFormData;
      };

export default function CustomerForm({ mode, customer }: Props) {
    const form =
        mode === 'create'
            ? CustomerController.store.form()
            : CustomerController.update.form(customer.id);

    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.customer} />

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={customer?.name ?? ''}
                            required
                            autoFocus
                            maxLength={160}
                            placeholder="PT Nusantara Retail"
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
                                defaultValue={customer?.email ?? ''}
                                maxLength={255}
                                placeholder="finance@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                defaultValue={customer?.phone ?? ''}
                                maxLength={40}
                                placeholder="+62 21 555 0101"
                            />
                            <InputError message={errors.phone} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Textarea
                            id="address"
                            name="address"
                            defaultValue={customer?.address ?? ''}
                            rows={5}
                            placeholder="Customer address"
                        />
                        <InputError message={errors.address} />
                    </div>

                    <Alert>
                        <AlertTitle>About Customer retirement</AlertTitle>
                        <AlertDescription>
                            Soft deletion removes this Customer from new and
                            resubmitted transaction drafts. Existing
                            transactions remain available and keep their live
                            Customer relationship.
                        </AlertDescription>
                    </Alert>

                    <div className="flex justify-end">
                        <Button disabled={processing}>
                            {processing
                                ? mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : mode === 'create'
                                  ? 'Create Customer'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
