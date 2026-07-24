import { Form } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import ProfitSchemePublicationController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemePublicationController';
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
    schemeId: number;
    versionId: number;
    versionNumber: number;
    disabled: boolean;
};

export default function PublishVersionDialog({
    schemeId,
    versionId,
    versionNumber,
    disabled,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button disabled={disabled}>
                    <LockKeyhole />
                    Publish
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Publish version {versionNumber}?</DialogTitle>
                    <DialogDescription>
                        Publishing requires active recipients and allocations
                        totaling exactly 100%. Published versions cannot be
                        edited.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ProfitSchemePublicationController.store.form({
                        scheme: schemeId,
                        version: versionId,
                    })}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.version} />
                            <InputError message={errors.allocations} />
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
                                        ? 'Publishing…'
                                        : 'Publish permanently'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
