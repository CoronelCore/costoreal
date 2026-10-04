import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const success: string | undefined = page.flash.success;
const actorName: string | undefined = page.props.auth.user?.name;
// @ts-expect-error Flash messages cannot be numeric payloads.
const unsafeFlash: number = page.flash.success;
// @ts-expect-error Flash is no longer a shared prop persisted in history.
const legacyFlash: string = page.props.flash.success;
router.on('finish', (event) => { const complete: boolean = event.detail.visit.completed; void complete; });
router.on('networkError', (event) => { const reason: string = event.detail.error.message; void reason; });
// @ts-expect-error v3 renamed the old network exception event.
router.on('exception', () => {});
void [success, actorName, unsafeFlash, legacyFlash];
