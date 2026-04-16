import { Head, router, usePage } from '@inertiajs/react';

interface Auth {
    user: { id: number; name: string; email: string } | null;
    roles: string[];
    assignments: { onderdeel_id: number; instrument_soort: string }[];
}

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Welkom" />
            <div className="flex min-h-screen items-center justify-center bg-background">
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-foreground">
                        Soli Muziekbibliotheek
                    </h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        Muziekvereniging Soli Driehuis
                    </p>

                    <div className="mt-8">
                        {auth.user ? (
                            <div className="space-y-4">
                                <p className="text-foreground">
                                    Welkom, <strong>{auth.user.name}</strong>
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {auth.user.email}
                                </p>
                                {auth.roles.length > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Rollen: {auth.roles.join(', ')}
                                    </p>
                                )}
                                {auth.assignments.length > 0 && (
                                    <div className="text-sm text-muted-foreground">
                                        <p>Bezetting:</p>
                                        <ul className="mt-1 space-y-1">
                                            {auth.assignments.map((a, i) => (
                                                <li key={i}>
                                                    Onderdeel #{a.onderdeel_id}{' '}
                                                    — {a.instrument_soort}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                <button
                                    onClick={() => router.post('/auth/logout')}
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Uitloggen
                                </button>
                            </div>
                        ) : (
                            <a
                                href="/auth/redirect"
                                className="inline-block rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                Inloggen via Soli Admin
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
