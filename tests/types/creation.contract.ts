import type { DraftWrite, CreationContext, ExpenseEditorDraft } from '../../resources/js/Types/creation';
// @ts-expect-error Reference rates cross HTTP as decimal text.
const wrongRate: CreationContext = { nombre:'Compra',descripcion:'',company_id:'',fecha_inicio:'',fecha_fin:'',moneda_trabajo:'USD',tipo_cambio:950 };
// @ts-expect-error Autosave requires its source revision and retry key.
const unversioned: DraftWrite = {draft_id:'id',step:'productos',payload:{context:wrongRate,product:null,expense:null}};
declare const expense: ExpenseEditorDraft;
// @ts-expect-error Pending attachment metadata is not a File or a private storage path.
const file: File = expense.receipt_name;
void wrongRate; void unversioned; void file;
