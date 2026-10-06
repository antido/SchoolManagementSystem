import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** Send an announcement to everyone, a role, or a class. */
export default function Announce({ roles, classes }: { roles: Option[]; classes: Option<number>[] }) {
    const { data, setData, post, processing, errors } = useForm({
        audience: 'everyone',
        role: '',
        school_class_id: '',
        title: '',
        message: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('notifications.send'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Send announcement" />
            <PageHeader
                title="Send announcement"
                intro="It appears in each recipient's notifications."
                breadcrumbs={[{ label: 'Notifications', href: route('notifications.index') }, { label: 'Send announcement' }]}
            />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <Card>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="audience" label="Send to" error={errors.audience} required>
                            <SelectInput
                                value={data.audience}
                                options={[
                                    { value: 'everyone', label: 'Everyone' },
                                    { value: 'role', label: 'Everyone with a role' },
                                    { value: 'class', label: 'A class (students and teachers)' },
                                ]}
                                onChange={(e) => setData('audience', e.target.value)}
                            />
                        </FormField>
                        {data.audience === 'role' && (
                            <FormField id="role" label="Role" error={errors.role} required>
                                <SelectInput value={data.role} options={roles} placeholder="Choose a role" onChange={(e) => setData('role', e.target.value)} />
                            </FormField>
                        )}
                        {data.audience === 'class' && (
                            <FormField id="school_class_id" label="Class" error={errors.school_class_id} required>
                                <SelectInput
                                    value={data.school_class_id}
                                    options={classes}
                                    placeholder="Choose a class"
                                    onChange={(e) => setData('school_class_id', e.target.value)}
                                />
                            </FormField>
                        )}
                        <FormField id="title" label="Title" error={errors.title} required className="sm:col-span-2">
                            <TextInput value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        </FormField>
                        <FormField id="message" label="Message" error={errors.message} required className="sm:col-span-2">
                            <Textarea rows={6} value={data.message} onChange={(e) => setData('message', e.target.value)} />
                        </FormField>
                    </div>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <PrimaryButton disabled={processing}>{processing ? 'Sending…' : 'Send announcement'}</PrimaryButton>
                    <Button variant="secondary" href={route('notifications.index')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
