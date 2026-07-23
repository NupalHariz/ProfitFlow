import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
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
    productId: number;
    productName: string;
};

export default function DeleteProductDialog({ productId, productName }: Props) {
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
                    <DialogTitle>Delete {productName}?</DialogTitle>
                    <DialogDescription>
                        This is a soft delete. The product will disappear from
                        master-data and transaction selection, but historical
                        transaction items, snapshots, rules, distributions, and
                        totals remain unchanged. A draft that already references
                        it must select an available replacement when
                        resubmitted.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ProductController.destroy.form(productId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.product} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Keep product
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Deleting…'
                                        : 'Soft delete product'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
