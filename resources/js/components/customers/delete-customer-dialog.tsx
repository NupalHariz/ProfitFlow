import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    customerId: number;
    customerName: string;
};

export default function DeleteCustomerDialog({
    customerId,
    customerName,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {customerName}?</DialogTitle>
                    <DialogDescription>
                        This Customer will no longer be available for new or
                        resubmitted transaction drafts. Existing transactions
                        remain available and keep their Customer relationship.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CustomerController.destroy.form(customerId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.customer} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Keep Customer
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Deleting…'
                                        : 'Soft delete Customer'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
