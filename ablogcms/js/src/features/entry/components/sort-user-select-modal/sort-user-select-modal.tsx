import UserSelect from '@features/user/components/user-select/user-select';
import { useNavigate } from 'react-router';
import { useState } from 'react';
import Modal from '../../../../components/modal/modal';
import HStack from '../../../../components/stack/h-stack';
import { useAcmsContext } from '../../../../stores/acms';
import VisuallyHidden from '../../../../components/visually-hidden';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface SortUserSelectModalProps extends React.ComponentPropsWithoutRef<typeof Modal> {}

const SortUserSelectModal = (props: SortUserSelectModalProps) => {
  const { context } = useAcmsContext();
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
      // ユーザーが選択されていない場合
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
        uid: id as string,
        order: 'sort-asc',
        admin: 'entry_index',
      })
    );
    props.onClose?.();
  };
  return (
    <Modal {...props} size="small" aria-labelledby="acms-admin-entry-sort-category-modal-title" isCentered>
      <Modal.Header>
        {ACMS.i18n('entry_index.sort_mode.modal.title', {
          object: ACMS.i18n('entry_index.sort_mode.object.user'),
        })}
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
                    object: ACMS.i18n('entry_index.sort_mode.object.user'),
                  })}
                </VisuallyHidden>
                <UserSelect
                  name="id"
                  defaultValue={context.uid?.toString() || undefined}
                  className="acms-admin-form-width-full"
                  isClearable={false}
                  placeholder={ACMS.i18n('select.not_selected')}
                  menuPortalTarget={document.body}
                />
              </label>
            </div>
            {errors?.id?.required && (
              <div role="alert" aria-live="assertive">
                <div>
                  <p className="acms-admin-text-error">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('entry_index.sort_mode.modal.error.id', {
                      object: ACMS.i18n('entry_index.sort_mode.object.user'),
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

export default SortUserSelectModal;
