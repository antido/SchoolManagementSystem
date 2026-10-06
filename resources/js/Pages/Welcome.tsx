import Button from '@/Components/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/Types';
import { Head, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth, app } = usePage<PageProps>().props;

    return (
        <PublicLayout fullBleed>
            <Head title="Welcome" />

            <section className="relative flex flex-1 items-center">
                {/* Photo by Arthur Krijgsman on Pexels (pexels.com/photo/4936021), free to use under the Pexels license. */}
                <img
                    src="/images/landing-classroom.jpg"
                    alt=""
                    className="absolute inset-0 size-full object-cover object-center"
                />

                <div className="relative mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                    <div className="max-w-2xl rounded-2xl bg-black/60 p-6 text-white backdrop-blur-sm sm:p-10">
                        <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">
                            Classes, coursework and grades in one place.
                        </h1>
                        <p className="mt-5 text-lg text-white/85">
                            {app.name} is where teachers post assignments and exams, students hand in
                            their work, and the school keeps track of attendance and results.
                        </p>
                        <div className="mt-8">
                            {auth.user ? (
                                <Button href={route('dashboard')}>Go to your dashboard</Button>
                            ) : (
                                <Button href={route('login')}>Log in</Button>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
