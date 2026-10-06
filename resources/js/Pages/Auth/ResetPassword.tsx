import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string | null }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        // The link normally carries the email; it may be missing if the link was edited.
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Choose a new password" />

            <h1 className="text-xl font-semibold">Choose a new password</h1>
            <p className="mt-1 text-sm text-muted">Use at least 8 characters. You will use it the next time you log in.</p>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="email" label="Email" error={errors.email}>
                    <TextInput type="email" name="email" value={data.email} autoComplete="username" onChange={(e) => setData('email', e.target.value)} />
                </FormField>

                <FormField id="password" label="New password" error={errors.password}>
                    <TextInput
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <FormField id="password_confirmation" label="Type the new password again" error={errors.password_confirmation}>
                    <TextInput
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </FormField>

                <PrimaryButton disabled={processing} className="w-full sm:w-auto">
                    {processing ? 'Saving…' : 'Save new password'}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
