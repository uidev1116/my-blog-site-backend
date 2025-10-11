import HStack from '../../../../components/stack/h-stack';
import CategorySelect from '../../../category/components/category-select/category-select';

interface EntryCreateFormProps {
  blogId: number;
  categoryId?: number;
}

const EntryCreateForm = ({ blogId, categoryId }: EntryCreateFormProps) => {
  return (
    <form id="entry-create-form" method="post" action="" encType="multipart/form-data">
      <HStack>
        <div style={{ maxWidth: '100%', width: '160px' }}>
          <CategorySelect
            name="cid"
            defaultValue={categoryId !== undefined ? { value: categoryId.toString(), label: '' } : undefined}
            placeholder={ACMS.i18n('entry_index.entry_create_form.category_select_placeholder')}
            key={categoryId}
          />
        </div>
        <div>
          <button type="submit" className="acms-admin-btn acms-admin-btn-success">
            {ACMS.i18n('entry_index.entry_create_form.submit')}
          </button>
        </div>
      </HStack>
      <input type="hidden" name="bid" defaultValue={blogId.toString()} />
      <input type="hidden" name="admin" defaultValue="entry_editor" />
      <input type="hidden" name="formToken" defaultValue={window.csrfToken} />
      <input type="hidden" name="ACMS_POST_2GET" defaultValue="on" />
    </form>
  );
};

export default EntryCreateForm;
