import { Form } from '@inertiajs/react';
import { CircleCheckBig, CircleOff, TriangleAlert } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    productId: number;
    productName: string;
    isActive: boolean;
    categoryIsActive: boolean;
};

export default function ProductStatusDialog({
    productId,
    productName,
    isActive,
    categoryIsActive,
}: Props) {
    const activating = !isActive;
    const activationBlocked = activating && !categoryIsActive;

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
                            ? `Activate ${productName}?`
                            : `Deactivate ${productName}?`}
                    </DialogTitle>
                    <DialogDescription>
                        {activating
                            ? 'The product can be selected for new or resubmitted transaction drafts when its current category is also active.'
                            : 'The product becomes unavailable to new or resubmitted transaction drafts. Existing transaction snapshots and completed financial history remain unchanged.'}
                    </DialogDescription>
                </DialogHeader>

                {activationBlocked && (
                    <Alert>
                        <TriangleAlert />
                        <AlertTitle>Activation is blocked</AlertTitle>
                        <AlertDescription>
                            Activate the current category first, or edit the
                            product and assign it to an active category.
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...ProductController.updateStatus.form(productId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_active"
                                value={activating ? '1' : '0'}
                            />
                            <InputError message={errors.product} />
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
                                <Button
                                    disabled={processing || activationBlocked}
                                >
                                    {processing
                                        ? activating
                                            ? 'Activating…'
                                            : 'Deactivating…'
                                        : activating
                                          ? 'Activate product'
                                          : 'Deactivate product'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
