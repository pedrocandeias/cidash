import { Form, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/comments';

export type CommentItem = {
    id: number;
    body: string;
    author: string | null;
    created_at: string;
    can_delete: boolean;
};

/**
 * Comments on any record (see CommentController).
 */
export default function CommentsThread({
    recordId,
    comments,
}: {
    recordId: string;
    comments: CommentItem[];
}) {
    const { t, locale } = useTranslation();

    return (
        <div className="space-y-4">
            <h2 className="text-base font-medium">{t('Comments')}</h2>

            {comments.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('No comments yet.')}
                </p>
            )}

            <ul className="space-y-3">
                {comments.map((comment) => (
                    <li key={comment.id} className="rounded-lg border p-3">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {comment.author ?? t('Deleted user')}
                            </span>
                            <span>
                                {formatDateTime(comment.created_at, locale)}
                            </span>
                            {comment.can_delete && (
                                <button
                                    type="button"
                                    className="ml-auto hover:text-foreground"
                                    onClick={() =>
                                        router.delete(destroy(comment.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {t('Delete')}
                                </button>
                            )}
                        </div>
                        <p className="mt-1 text-sm whitespace-pre-line">
                            {comment.body}
                        </p>
                    </li>
                ))}
            </ul>

            <Form
                {...store.form(recordId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-2"
            >
                {({ processing, errors }) => (
                    <>
                        <textarea
                            name="body"
                            rows={3}
                            required
                            aria-label={t('Write a comment')}
                            placeholder={t('Write a comment')}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={errors.body} />
                        <Button size="sm" disabled={processing}>
                            {t('Comment')}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
