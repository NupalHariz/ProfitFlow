import { Form } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
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

export default function TransactionCancellationDialog({
    transactionId,
}: {
    transactionId: number;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    <Ban />
                    Cancel transaction
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel this transaction?</DialogTitle>
                    <DialogDescription>
                        This transaction will no longer be editable or available
                        for completion. It will remain in transaction history.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...TransactionController.cancel.form(transactionId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.transaction} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Keep Transaction
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Cancelling…'
                                        : 'Cancel Transaction'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
