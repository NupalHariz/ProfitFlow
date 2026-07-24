import { Form } from '@inertiajs/react';
import { CircleCheckBig } from 'lucide-react';
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

export default function TransactionCompletionDialog({
    transactionId,
}: {
    transactionId: number;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <CircleCheckBig />
                    Complete transaction
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Complete this transaction?</DialogTitle>
                    <DialogDescription>
                        Completing ends draft editing, finalizes totals and
                        applied rules, then starts profit distribution.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...TransactionController.complete.form(transactionId)}
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
                                        Keep editing
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Completing…'
                                        : 'Complete transaction'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
