import { Head } from '@inertiajs/react';

export default function Inicio({ produtos }: { produtos: number }) {
    return (
        <>
            <Head title="Início" />
            <div className="min-h-screen bg-cream flex items-center justify-center">
                <div className="bg-white rounded-lg border border-gray-light p-8 text-center">
                    <div className="text-charcoal text-4xl font-bold" style={{ fontFamily: 'var(--font-condensed)' }}>
                        GESTOQUE
                    </div>
                    <p className="text-gray-text mt-2">
                        Laravel + Inertia + React funcionando.
                    </p>
                    <p className="text-charcoal mt-4">
                        Produtos no banco: <span className="text-red font-bold">{produtos}</span>
                    </p>
                </div>
            </div>
        </>
    );
}