import { Form } from '@inertiajs/react';
import { CircleCheckBig, CircleOff } from 'lucide-react';
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
    isActive: boolean;
};

export default function InvestorStatusDialog({
    investorId,
    investorName,
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
                            ? `Activate ${investorName}?`
                            : `Deactivate ${investorName}?`}
                    </DialogTitle>
                    <DialogDescription>
                        {activating
                            ? 'This changes only the Investor profile. A linked Profit Recipient keeps its own existing status.'
                            : 'Deactivating this Investor changes only the Investor profile. It does not deactivate or modify a linked Profit Recipient, published allocations, or historical distributions.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...InvestorController.updateStatus.form(investorId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_active"
                                value={activating ? '1' : '0'}
                            />
                            <InputError message={errors.investor} />
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
                                          ? 'Activate Investor'
                                          : 'Deactivate Investor'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
