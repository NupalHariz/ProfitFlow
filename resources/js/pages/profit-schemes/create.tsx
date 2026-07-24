import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export default function CreateProfitScheme() {
    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [codeEdited, setCodeEdited] = useState(false);
    const [isActive, setIsActive] = useState(true);

    function changeName(value: string): void {
        setName(value);

        if (!codeEdited) {
            setCode(slugSuggestion(value));
        }
    }

    return (
        <>
            <Head title="Create profit scheme" />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProfitSchemeController.index()}>
                            <ArrowLeft />
                            Profit schemes
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create profit scheme
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create a scheme for versioned profit-sharing rules.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Scheme details</CardTitle>
                        <CardDescription>
                            Use a short, unique code in lowercase.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ProfitSchemeController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <InputError message={errors.scheme} />

                                    <div className="grid gap-2">
                                        <Label htmlFor="name" required>
                                            Name
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            value={name}
                                            onChange={(event) =>
                                                changeName(event.target.value)
                                            }
                                            required
                                            autoFocus
                                            placeholder="Electronics profit sharing"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="code" required>
                                            Code
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            value={code}
                                            onChange={(event) => {
                                                setCode(event.target.value);
                                                setCodeEdited(true);
                                            }}
                                            required
                                            className="font-mono"
                                            placeholder="electronics-profit-sharing"
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            placeholder="Explain when this family of rules is used."
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="flex items-center gap-3 rounded-md border p-4">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value={isActive ? '1' : '0'}
                                        />
                                        <Checkbox
                                            id="is_active"
                                            checked={isActive}
                                            onCheckedChange={(checked) =>
                                                setIsActive(checked === true)
                                            }
                                        />
                                        <div>
                                            <Label htmlFor="is_active">
                                                Active scheme
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Active schemes can apply to new
                                                transactions.
                                            </p>
                                        </div>
                                        <InputError
                                            message={errors.is_active}
                                        />
                                    </div>

                                    <div className="flex justify-end">
                                        <Button disabled={processing}>
                                            {processing
                                                ? 'Creating…'
                                                : 'Create scheme'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function slugSuggestion(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

CreateProfitScheme.layout = {
    breadcrumbs: [
        {
            title: 'Profit schemes',
            href: ProfitSchemeController.index(),
        },
        {
            title: 'Create',
            href: ProfitSchemeController.create(),
        },
    ],
};
