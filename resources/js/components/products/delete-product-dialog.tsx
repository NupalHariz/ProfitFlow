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
                        The product will no longer be available for new or
                        updated draft transactions. Existing completed
                        transactions and distributions remain unchanged. Drafts
                        using it must choose another product before saving.
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
                                        : 'Delete product'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
