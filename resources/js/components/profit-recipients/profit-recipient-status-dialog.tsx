import { Form } from '@inertiajs/react';
import { CircleCheckBig, CircleOff } from 'lucide-react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
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
    recipientId: number;
    recipientName: string;
    isActive: boolean;
};

export default function ProfitRecipientStatusDialog({
    recipientId,
    recipientName,
    isActive,
}: Props) {
    const activating = !isActive;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {activating ? <CircleCheckBig /> : <CircleOff />}
                    {activating ? 'Activate' : 'Deactivate'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {activating
                            ? `Activate ${recipientName}?`
                            : `Deactivate ${recipientName}?`}
                    </DialogTitle>
                    <DialogDescription>
                        {activating
                            ? 'The recipient becomes available for editable allocations. A linked Investor keeps its current status.'
                            : 'The recipient cannot be added to editable allocations. Existing allocations and distributions remain unchanged.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ProfitRecipientController.updateStatus.form(
                        recipientId,
                    )}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_active"
                                value={activating ? '1' : '0'}
                            />
                            <InputError message={errors.recipient} />
                            <InputError message={errors.is_active} />
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
                                        ? activating
                                            ? 'Activating…'
                                            : 'Deactivating…'
                                        : activating
                                          ? 'Activate Profit Recipient'
                                          : 'Deactivate Profit Recipient'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
