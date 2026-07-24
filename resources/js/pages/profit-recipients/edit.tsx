import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import ProfitRecipientForm from '@/components/profit-recipients/profit-recipient-form';
import ProfitRecipientStatusDialog from '@/components/profit-recipients/profit-recipient-status-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProfitRecipientData, ProfitRecipientInvestor } from '@/types';

type PageProps = {
    recipient: ProfitRecipientData;
    investor: ProfitRecipientInvestor | null;
};

export default function EditProfitRecipient({
    recipient,
    investor,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${recipient.display_name}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link
                            href={ProfitRecipientController.show(recipient.id)}
                        >
                            <ArrowLeft />
                            {recipient.display_name}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit Profit Recipient
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Update code and display name.
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recipient profile</CardTitle>
                            <CardDescription>
                                Recipient type and linked Investor cannot be
                                changed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ProfitRecipientForm
                                mode="edit"
                                recipient={recipient}
                                investor={investor}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                            <CardDescription>
                                Manage whether this recipient can be used in new
                                allocations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ProfitRecipientStatusDialog
                                recipientId={recipient.id}
                                recipientName={recipient.display_name}
                                isActive={recipient.is_active}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

EditProfitRecipient.layout = {
    breadcrumbs: [
        {
            title: 'Profit Recipients',
            href: ProfitRecipientController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
