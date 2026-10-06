import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Reset your password" />

            <h1 className="text-xl font-semibold">Reset your password</h1>
            <p className="mt-1 text-sm text-muted">
                Enter the email address your school registered for you. We will send a link to choose a new password.
            </p>

            {status && (
                <p className="mt-4 rounded-lg bg-success-soft px-3 py-2 text-sm font-medium text-success" role="status">
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

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link href={route('login')} className="link text-sm">
                        Back to log in
                    </Link>
                    <PrimaryButton disabled={processing}>{processing ? 'Sending…' : 'Send reset link'}</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
