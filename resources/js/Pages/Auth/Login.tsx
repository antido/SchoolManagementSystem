import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <h1 className="text-xl font-semibold">Log in to your account</h1>
            <p className="mt-1 text-sm text-muted">
                Use the email address your school registered for you.
            </p>

            {status && (
                <p className="mt-4 rounded-lg bg-success-soft px-3 py-2 text-sm font-medium text-success">
                    {status}
                </p>
            )}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="email" label="Email" error={errors.email}>
                    <TextInput
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        isFocused
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField id="password" label="Password" error={errors.password}>
                    <TextInput
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <label className="flex min-h-10 items-center gap-2 text-sm">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        Remember me
                    </label>

                    {canResetPassword && (
                        <Link href={route('password.request')} className="link text-sm">
                            Forgot your password?
                        </Link>
                    )}
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    {processing ? 'Logging in…' : 'Log in'}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
