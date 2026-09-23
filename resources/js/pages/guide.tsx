import { Head } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { guide } from '@/routes';

export default function Guide({ html }: { html: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Guide')} />

            {/* Rendered from resources/guide/<locale>.md on the server. */}
            <article
                className="mx-auto max-w-3xl space-y-4 px-4 py-6 text-sm leading-relaxed [&_a]:underline [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:tracking-tight [&_h2]:pt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ul]:list-disc [&_ul]:space-y-1"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </>
    );
}

Guide.layout = {
    breadcrumbs: [{ title: 'Guide', href: guide() }],
};
