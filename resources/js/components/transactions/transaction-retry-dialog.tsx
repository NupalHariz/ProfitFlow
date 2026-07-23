import { Form } from '@inertiajs/react';
import { RefreshCcw } from 'lucide-react';
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

type Props = {
    transactionId: number;
    mode: 'retry' | 'redrive';
};

export default function TransactionRetryDialog({ transactionId, mode }: Props) {
    const isRedrive = mode === 'redrive';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant={isRedrive ? 'outline' : 'default'}>
                    <RefreshCcw />
                    {isRedrive ? 'Requeue distribution' : 'Retry distribution'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isRedrive
                            ? 'Requeue this distribution?'
                            : 'Retry this distribution?'}
                    </DialogTitle>
                    <DialogDescription>
                        This redispatches the existing frozen transaction. It
                        does not recalculate financial snapshots or resolve a
                        newer rule version. Duplicate delivery is safe because
                        database processing is idempotent.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...TransactionController.retryDistribution.form(
                        transactionId,
                    )}
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
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Queueing…'
                                        : isRedrive
                                          ? 'Requeue frozen transaction'
                                          : 'Queue retry'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
