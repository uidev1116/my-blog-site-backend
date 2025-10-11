import { forwardRef, useCallback, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Icon } from '@components/icon';
import { Tooltip } from '../../../../components/tooltip';

import {
  Filter,
  FilterBody,
  FilterFooter,
  FilterGroupV2,
  FilterItem,
  FilterActionGroup,
  FilterItemLabel,
  FilterFormControl,
} from '../../../../components/filter';

import RichSelect from '../../../../components/rich-select/rich-select';

import { MODULE_STATUSES, MODULE_SCOPE, MODULE_BLOG_AXIS } from '../../constants';
import { useAcmsContext } from '../../../../stores/acms';

interface ModuleFilterProps extends React.FormHTMLAttributes<HTMLFormElement> {
  onSubmit?: (event: React.FormEvent<HTMLFormElement>) => void;
}

interface StatusOptionsType {
  value: string;
  label: string;
}

interface ScopeOptionsType {
  value: string;
  label: string;
}

const statusOptions = MODULE_STATUSES.map((status) => ({
  value: status.value,
  label: status.label,
}));

const scopeOptions = MODULE_SCOPE.map((scope) => ({
  value: scope.value,
  label: scope.label,
}));

const blogAxisOptions = MODULE_BLOG_AXIS.map((blogAxis) => ({
  value: blogAxis.value,
  label: blogAxis.label,
}));

const ModuleFilter = forwardRef<HTMLFormElement, ModuleFilterProps>(({ onSubmit }, ref) => {
  const { context } = useAcmsContext();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [searchTimeout, setSearchTimeout] = useState<NodeJS.Timeout | null>(null);
  const [localKeyword, setLocalKeyword] = useState(searchParams.get('keyword') || '');

  const handleSubmit = useCallback(
    (event: React.FormEvent<HTMLFormElement>) => {
      const form = event.currentTarget;
      const submitter = event.nativeEvent instanceof SubmitEvent ? event.nativeEvent.submitter : null;
      const formData = new FormData(form, submitter);
      const intent = formData.get('intent') as 'search' | 'clear';

      if (intent === 'clear') {
        setLocalKeyword('');
      }

      if (onSubmit) {
        onSubmit(event);
      }
    },
    [onSubmit]
  );

  const handleKeywordChange = useCallback(
    (event: React.ChangeEvent<HTMLInputElement>) => {
      setLocalKeyword(event.target.value);

      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      const newTimeout = setTimeout(() => {
        const params = new URLSearchParams(window.location.search);
        params.set('keyword', event.target.value);
        navigate(`?${params.toString()}`, { replace: true });
      }, 250);

      setSearchTimeout(newTimeout);
    },
    [navigate, searchTimeout]
  );

  return (
    <form
      ref={ref}
      id="module-filter-form"
      action=""
      method="post"
      className="acms-admin-form"
      role="search"
      onSubmit={handleSubmit}
    >
      <Filter>
        <FilterBody>
          <FilterGroupV2>
            <FilterItem id="filter-item-keyword">
              <FilterFormControl>
                <FilterItemLabel htmlFor="filter-quick-search">
                  {ACMS.i18n('module_index.filter.quick_search')}
                </FilterItemLabel>

                <input
                  type="search"
                  name="keyword"
                  value={localKeyword}
                  className="acms-admin-form-width-full"
                  id="filter-keyword"
                  placeholder={ACMS.i18n('module_index.filter.quick_search_placeholder')}
                  onChange={handleKeywordChange}
                />
                <input type="hidden" name="query[]" defaultValue="keyword" />
              </FilterFormControl>
            </FilterItem>

            <FilterItem id="filter-item-status">
              <FilterFormControl>
                <FilterItemLabel htmlFor="filter-status">{ACMS.i18n('module_index.filter.status')}</FilterItemLabel>

                <RichSelect<StatusOptionsType>
                  inputId="filter-status"
                  name="status"
                  className="acms-admin-form-width-full"
                  defaultValue={statusOptions.find((option) => option.value === searchParams.get('status'))}
                  key={searchParams.get('status')}
                  options={statusOptions}
                  isClearable
                  placeholder={ACMS.i18n('select.not_selected')}
                />
                <input type="hidden" name="query[]" defaultValue="status" />
              </FilterFormControl>
            </FilterItem>

            <FilterItem id="filter-item-scope">
              <FilterFormControl>
                <FilterItemLabel htmlFor="filter-scope">{ACMS.i18n('module_index.filter.scope')}</FilterItemLabel>
                <RichSelect<ScopeOptionsType>
                  inputId="filter-scope"
                  name="scope"
                  className="acms-admin-form-width-full"
                  defaultValue={scopeOptions.find((option) => option.value === searchParams.get('scope'))}
                  key={searchParams.get('scope')}
                  options={scopeOptions}
                  isClearable
                  placeholder={ACMS.i18n('select.not_selected')}
                />
                <input type="hidden" name="query[]" defaultValue="scope" />
              </FilterFormControl>
            </FilterItem>

            <FilterItem id="filter-item-blog-axis">
              <FilterFormControl>
                <FilterItemLabel htmlFor="filter-blog-axis" className="acms-admin-d-flex acms-admin-align-items-center">
                  <span className="acms-admin-margin-right-mini">{ACMS.i18n('module_index.filter.blog_axis')}</span>
                  <button
                    type="button"
                    className="acms-admin-btn-unstyled"
                    data-tooltip-id="tooltip-blog-axis"
                    data-tooltip-html={ACMS.i18n('module_index.filter.tooltip.blog_axis')}
                    aria-label={ACMS.i18n('tooltip.trigger.label')}
                  >
                    <i className="acms-admin-icon-midium acms-admin-icon-tooltip" />
                  </button>
                  <Tooltip id="tooltip-blog-axis" place="top" />
                </FilterItemLabel>
                <select
                  id="filter-blog-axis"
                  name="axis"
                  className="acms-admin-form-width-full"
                  defaultValue={searchParams.get('axis') || undefined}
                  key={searchParams.get('axis')}
                >
                  {blogAxisOptions.map((blogAxis) => (
                    <option key={blogAxis.value} value={blogAxis.value}>
                      {blogAxis.label}
                    </option>
                  ))}
                </select>
                <input type="hidden" name="query[]" defaultValue="axis" />
              </FilterFormControl>
            </FilterItem>
          </FilterGroupV2>
        </FilterBody>
        <FilterFooter>
          <FilterActionGroup>
            <button
              type="submit"
              name="intent"
              value="search"
              className="acms-admin-btn-admin acms-admin-btn-admin-info acms-admin-btn-admin-search"
            >
              <Icon name="search" />
              {ACMS.i18n('entry_index.filter.submit')}
            </button>
            <button type="submit" name="intent" value="clear" className="acms-admin-btn-admin">
              {ACMS.i18n('entry_index.filter.clear')}
            </button>
          </FilterActionGroup>
        </FilterFooter>
      </Filter>
      <input type="hidden" name="bid" defaultValue={context.bid} key={context.bid} />
      <input type="hidden" name="order" defaultValue={context.order} key={context.order} />
      <input type="hidden" name="admin" defaultValue="module_index" />
    </form>
  );
});

ModuleFilter.displayName = 'ModuleFilter';

export default ModuleFilter;
