import CategorySelect from '@features/category/components/category-select/category-select';
import { useNavigate, useSearchParams } from 'react-router';
import { useState } from 'react';
import Modal from '../../../../components/modal/modal';
import HStack from '../../../../components/stack/h-stack';
import { useAcmsContext } from '../../../../stores/acms';
import VisuallyHidden from '../../../../components/visually-hidden';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface SortCategorySelectModalProps extends React.ComponentPropsWithoutRef<typeof Modal> {}

const SortCategorySelectModal = (props: SortCategorySelectModalProps) => {
  const { context } = useAcmsContext();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const [errors, setErrors] = useState<Record<string, Record<string, boolean>>>({
    id: {
      required: false,
    },
  });
  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    setErrors({});
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    const id = formData.get('id');
    if (id === null || id === '') {
      // カテゴリーが選択されていない場合
      setErrors((prev) => ({
        ...prev,
        id: {
          required: true,
        },
      }));
      return;
    }
    navigate(
      ACMS.Library.acmsLink({
        bid: context.bid,
        cid: id as string,
        order: 'sort-asc',
        admin: 'entry_index',
        searchParams: new URLSearchParams({
          _cid: (id as string).toString(),
        }),
      })
    );
    props.onClose?.();
  };
  return (
    <Modal {...props} size="small" aria-labelledby="acms-admin-entry-sort-category-modal-title" isCentered>
      <Modal.Header>
        {ACMS.i18n('entry_index.sort_mode.modal.title', { object: ACMS.i18n('entry_index.sort_mode.object.category') })}
      </Modal.Header>
      <Modal.Body>
        <form id="sort-category-select-form" onSubmit={handleSubmit} className="acms-admin-form">
          <div className="acms-admin-sort-context-select">
            <p className="acms-admin-sort-context-select-help-message">
              {ACMS.i18n('entry_index.sort_mode.modal.help_message')}
            </p>
            <div className="acms-admin-sort-context-select-form-select-container">
              {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
              <label className="acms-admin-sort-context-select-form-select">
                <VisuallyHidden>
                  {ACMS.i18n('entry_index.sort_mode.modal.title', {
                    object: ACMS.i18n('entry_index.sort_mode.object.category'),
                  })}
                </VisuallyHidden>
                <CategorySelect
                  name="id"
                  defaultValue={searchParams.get('_cid') || context.cid?.toString()}
                  noOption
                  placeholder={ACMS.i18n('select.not_selected')}
                  isClearable={false}
                  menuPortalTarget={document.body}
                  className="acms-admin-form-width-full"
                />
              </label>
            </div>
            {errors?.id?.required && (
              <div role="alert" aria-live="assertive">
                <div>
                  <p className="acms-admin-text-error">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('entry_index.sort_mode.modal.error.id', {
                      object: ACMS.i18n('entry_index.sort_mode.object.category'),
                    })}
                  </p>
                </div>
              </div>
            )}
          </div>
        </form>
      </Modal.Body>
      <Modal.Footer>
        <HStack display="inline-flex">
          <button type="button" onClick={props.onClose} className="acms-admin-btn">
            {ACMS.i18n('entry_index.sort_mode.modal.cancel')}
          </button>
          <button
            form="sort-category-select-form"
            type="submit"
            className="acms-admin-btn-admin acms-admin-btn-admin-primary"
          >
            {ACMS.i18n('entry_index.sort_mode.modal.apply')}
          </button>
        </HStack>
      </Modal.Footer>
    </Modal>
  );
};

export default SortCategorySelectModal;
