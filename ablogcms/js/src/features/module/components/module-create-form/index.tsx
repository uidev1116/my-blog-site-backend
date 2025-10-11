import HStack from '../../../../components/stack/h-stack';

interface ModuleCreateFormProps {
  blogId: number;
}
const ModuleCreateForm = ({ blogId }: ModuleCreateFormProps) => {
  return (
    <form id="module-create-form" method="post" action="" encType="multipart/form-data">
      <HStack>
        <div>
          <button type="submit" className="acms-admin-btn acms-admin-btn-success">
            {ACMS.i18n('module_index.module_create_form.submit')}
          </button>
        </div>
      </HStack>
      <input type="hidden" name="bid" defaultValue={blogId.toString()} />
      <input type="hidden" name="admin" defaultValue="module_edit" />
      <input type="hidden" name="edit" defaultValue="insert" />
      <input type="hidden" name="query[]" defaultValue="edit" />
      <input type="hidden" name="ACMS_POST_2GET" defaultValue="New Module" />
      <input type="hidden" name="formToken" defaultValue={window.csrfToken} />
    </form>
  );
};
export default ModuleCreateForm;
