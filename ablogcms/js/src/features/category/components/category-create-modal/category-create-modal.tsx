import { useCallback, useState } from 'react';
import { isFetchError } from '../../../../lib/fetch-client';
import Modal from '../../../../components/modal/modal';
import CategorySelect from '../category-select/category-select';
import { CreateCategoryFailedResponse, createCategory } from '../../api';
import { CreatedCategoryDTO } from '../../types';

interface CategoryCreateModalProps extends Pick<React.ComponentPropsWithoutRef<typeof Modal>, 'isOpen' | 'onClose'> {
  onCreate?: (category: CreatedCategoryDTO) => void;
}

export type CreateCategoryErrorMap = Record<string, Record<string, boolean>>;

const transformErrors = (errors: CreateCategoryFailedResponse['errors']): CreateCategoryErrorMap =>
  errors.reduce((acc: CreateCategoryErrorMap, error: CreateCategoryFailedResponse['errors'][0]) => {
    if (error.field && error.option) {
      if (!acc[error.field]) {
        acc[error.field] = {};
      }
      acc[error.field][error.option] = true;
    }
    return acc;
  }, {});

const CategoryCreateModal = ({ isOpen, onClose, onCreate }: CategoryCreateModalProps) => {
  const [errors, setErrors] = useState<CreateCategoryErrorMap>({
    name: {
      required: false,
      maxlength: false,
    },
    code: {
      required: false,
      double: false,
      reserved: false,
      string: false,
      maxlength: false,
    },
    scope: {
      tree: false,
      shared: false,
    },
  });
  const handleSubmit = useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      if (!(event.target instanceof HTMLFormElement)) {
        return;
      }
      setErrors({});
      try {
        // ToDo: ローディング表示
        const formData = new FormData(event.target);
        const category = await createCategory(formData);
        onCreate?.(category);
      } catch (error) {
        if (isFetchError<CreateCategoryFailedResponse>(error)) {
          if (error.response?.data.errors) {
            const errors = transformErrors(error.response.data.errors);
            setErrors(errors);
          }
        }
        console.error(error); // eslint-disable-line no-console
      }
    },
    [onCreate]
  );

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="small" aria-labelledby="acms-admin-category-create-modal-title">
      <Modal.Header>{ACMS.i18n('category.add_title')}</Modal.Header>
      <Modal.Body>
        <form
          id="category-create-form"
          className="acms-admin-form"
          style={{ marginTop: '20px' }}
          onSubmit={handleSubmit}
        >
          <input type="hidden" name="category[]" value="name" />
          <input type="hidden" name="category[]" value="code" />
          <input type="hidden" name="category[]" value="parent" />
          <input type="hidden" name="category[]" value="scope" />
          <input type="hidden" name="category[]" value="status" />
          <input type="hidden" name="category[]" value="indexing" />

          <input type="hidden" name="name:validator#required" id="validator-name-required" />
          <input type="hidden" name="name:validator#maxlength" value="255" id="validator-name-maxlength" />
          <input type="hidden" name="code:validator#required" id="validator-code-required" />
          <input type="hidden" name="code:validator#maxlength" value="255" id="validator-code-maxlength" />
          <input type="hidden" name="code:validator#double" id="validator-code-double" />
          <input type="hidden" name="code:validator#reserved" id="validator-code-reserved" />
          <input type="hidden" name="scope:validator#required" id="validator-scope-required" />

          <input type="hidden" name="status" value="open" />
          <input type="hidden" name="indexing" value="on" />

          <div className="acms-admin-form-group">
            <label htmlFor="input-select-category-parent" className="acms-admin-margin-bottom-mini">
              {ACMS.i18n('category.parent')}
            </label>
            <CategorySelect
              id="parent"
              inputId="input-select-category-parent"
              name="parent"
              isCreatable={false}
              menuPortalTarget={document.body}
            />
          </div>

          <div className="acms-admin-form-group">
            <label htmlFor="input-text-category-name" className="acms-admin-margin-bottom-mini">
              {ACMS.i18n('category.name')}
            </label>
            <input id="input-text-category-name" type="text" name="name" className="acms-admin-form-width-full" />
            <div role="alert" aria-live="assertive">
              {errors?.name?.required && (
                <div data-validator-label="validator-name-required" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.name.required')}
                  </p>
                </div>
              )}
              {errors?.name?.maxlength && (
                <div data-validator-label="validator-name-maxlength" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.name.maxlength')}
                  </p>
                </div>
              )}
            </div>
          </div>

          <div className="acms-admin-form-group">
            <label htmlFor="input-text-category-code" className="acms-admin-margin-bottom-mini">
              {ACMS.i18n('category.code')}
            </label>
            <input id="input-text-category-code" type="text" name="code" className="acms-admin-form-width-full" />
            <div role="alert" aria-live="assertive">
              {errors?.code?.required && (
                <div data-validator-label="validator-code-required" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.code.required')}
                  </p>
                </div>
              )}
              {errors?.code?.maxlength && (
                <div data-validator-label="validator-code-maxlength" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.code.maxlength')}
                  </p>
                </div>
              )}
              {errors?.code?.double && (
                <div data-validator-label="validator-code-double" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.code.double')}
                  </p>
                </div>
              )}
              {errors?.code?.reserved && (
                <div data-validator-label="validator-code-reserved" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.code.reserved')}
                  </p>
                </div>
              )}
              {errors?.code?.string && (
                <div data-validator-label="validator-code-string" className="validator-result-0">
                  <p className="error-text">
                    <span className="acms-admin-icon acms-admin-icon-attention" aria-hidden="true" />
                    {ACMS.i18n('category.error.code.string')}
                  </p>
                </div>
              )}
            </div>
          </div>

          <div className="acms-admin-form-group">
            <label htmlFor="input-checkbox-global" className="acms-admin-margin-bottom-mini">
              {ACMS.i18n('category.scope')}
            </label>
            <br />
            <input type="hidden" name="scope" value="local" />
            <div className="acms-admin-form-checkbox">
              <input type="checkbox" name="scope" value="global" id="input-checkbox-global" />
              <label htmlFor="input-checkbox-global">
                <i className="acms-admin-ico-checkbox" />
                {ACMS.i18n('category.scope_global')}
              </label>
            </div>
            {errors?.scope?.tree && (
              <div role="alert" aria-live="assertive">
                <div data-validator-label="validator-scope-tree" className="validator-result-0">
                  <p className="error-text">{ACMS.i18n('category.error.scope.tree')}</p>
                </div>
              </div>
            )}
            {errors?.scope?.shared && (
              <div role="alert" aria-live="assertive">
                <div data-validator-label="validator-scope-tree" className="validator-result-0">
                  <p className="error-text">{ACMS.i18n('category.error.scope.shared')}</p>
                </div>
              </div>
            )}
          </div>
          <input type="hidden" name="formToken" value={window.csrfToken} />
        </form>
      </Modal.Body>
      <Modal.Footer>
        <div>
          <button type="submit" className="acms-admin-btn" form="category-create-form">
            {ACMS.i18n('category.add')}
          </button>
        </div>
      </Modal.Footer>
    </Modal>
  );
};

export default CategoryCreateModal;
