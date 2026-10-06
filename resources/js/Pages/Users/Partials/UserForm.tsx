import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { UserSummary } from '@/Types/users';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * The form for creating and editing an account. Pass `user` when editing.
 * When people edit their own account, role and status are not shown: the
 * server refuses those changes anyway.
 */
export default function UserForm({
    user,
    roles,
    isSelf = false,
}: {
    user?: UserSummary;
    roles: Option[];
    isSelf?: boolean;
}) {
    const editing = user !== undefined;

    const { data, setData, post, put, processing, errors, transform } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        role: user?.role ?? '',
        is_active: user?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            // Leave out the fields the server will not accept for your own account.
            transform((values) => {
                const { role, is_active, ...rest } = values;
                return isSelf ? rest : { ...rest, role, is_active };
            });
            put(route('users.update', user.id));
        } else {
            post(route('users.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <Card title="Account">
                <div className="space-y-5">
                    <FormField id="name" label="Full name" error={errors.name} required>
                        <TextInput
                            value={data.name}
                            autoComplete="off"
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <FormField id="email" label="Email" error={errors.email} required>
                        <TextInput
                            type="email"
                            value={data.email}
                            autoComplete="off"
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>

                    {!isSelf && (
                        <FormField id="role" label="Role" error={errors.role} required>
                            <SelectInput
                                value={data.role}
                                options={roles}
                                placeholder="Choose a role"
                                onChange={(e) => setData('role', e.target.value)}
                            />
                        </FormField>
                    )}

                    {!isSelf && (
                        <label className="flex items-start gap-3">
                            <Checkbox
                                className="mt-0.5"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-medium">Active</span>
                                <span className="block text-sm text-muted">
                                    Inactive accounts cannot sign in.
                                </span>
                            </span>
                        </label>
                    )}
                </div>
            </Card>

            <Card title={editing ? 'Change password' : 'Password'}>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="password"
                        label="Password"
                        error={errors.password}
                        hint={editing ? 'Leave empty to keep the current password.' : 'At least 8 characters.'}
                        required={!editing}
                    >
                        <TextInput
                            type="password"
                            value={data.password}
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        id="password_confirmation"
                        label="Confirm password"
                        error={errors.password_confirmation}
                        required={!editing}
                    >
                        <TextInput
                            type="password"
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Create account'}
                </PrimaryButton>
                <Button
                    variant="secondary"
                    href={editing ? route('users.show', user.id) : route('users.index')}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
