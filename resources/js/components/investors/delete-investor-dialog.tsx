import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
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
    investorId: number;
    investorName: string;
    hasLinkedRecipient: boolean;
};

export default function DeleteInvestorDialog({
    investorId,
    investorName,
    hasLinkedRecipient,
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
                    <DialogTitle>Delete {investorName}?</DialogTitle>
                    <DialogDescription>
                        {hasLinkedRecipient
                            ? 'Investors linked to a Profit Recipient cannot be deleted. Use deactivation instead. The backend will enforce this guard regardless of recipient status or financial usage.'
                            : 'This is a soft delete. The Investor will disappear from normal management. No Profit Recipient, allocation, distribution, or financial history is changed.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...InvestorController.destroy.form(investorId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.investor} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Keep Investor
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Checking…'
                                        : hasLinkedRecipient
                                          ? 'Confirm deletion check'
                                          : 'Soft delete Investor'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
