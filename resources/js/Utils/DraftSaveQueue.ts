/** Coalesce concurrent saves and release the slot even after a no-op or failed attempt. */
export class DraftSaveQueue {
    private active: Promise<boolean> | null = null;

    run(operation: () => Promise<boolean>): Promise<boolean> {
        if (this.active) return this.active;
        const task = Promise.resolve().then(operation);
        this.active = task;
        const release = () => { if (this.active === task) this.active = null; };
        task.then(release, release);
        return task;
    }
}
