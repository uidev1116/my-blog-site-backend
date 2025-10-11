import { forwardRef, useCallback, useMemo, useRef } from 'react';
import { useSearchParams } from 'react-router';
import { Icon } from '@components/icon';
import { Tooltip } from '../../../../components/tooltip';

import {
  Filter,
  FilterBody,
  FilterFooter,
  FilterGroupV2,
  FilterInner,
  FilterItem,
  FilterActionGroup,
  FilterItemLabel,
  FilterToggleButton,
  FilterFormControl,
} from '../../../../components/filter';

import useLocalStorage from '../../../../hooks/use-local-storage';
import CategorySelect from '../../../category/components/category-select/category-select';
import { ENTRY_STATUSES, FILTERABLE_ENTRY_COLUMN_IDS } from '../../constants';
import { CategoryOption } from '../../../category/types';
import HStack from '../../../../components/stack/h-stack';
import { EntrySortType, type EntryType } from '../../types';
import UserSelect from '../../../user/components/user-select/user-select';
import BlogSelect from '../../../blog/components/blog-select/blog-select';
import DataviewFilter from '../../../../components/dataview/dataview-filter';
import {
  type Filter as FilterType,
  isCustomAccessorColumn,
  type Column,
  CustomAccessorColumn,
} from '../../../../components/dataview/types';
import useOptionDataSWR from '../../hooks/use-option-data-swr';
import { useAcmsContext } from '../../../../stores/acms';
import { nl2br } from '../../../../utils/string';
import RichSelect from '../../../../components/rich-select/rich-select';

interface EntryFilterProps extends React.FormHTMLAttributes<HTMLFormElement> {
  columns: Column<EntryType>[];
  ignoredFilters?: string[];
  sortType?: EntrySortType;
  onSubmit?: (event: React.FormEvent<HTMLFormElement>) => void;
}

const statusOptions = ENTRY_STATUSES.filter((status) => status.value !== 'trash').map((status) => ({
  value: status.value,
  label: status.label,
}));

const EntryFilter = forwardRef<HTMLFormElement, EntryFilterProps>(
  ({ columns, ignoredFilters: ignoredFiltersProp = [], sortType, onSubmit }, ref) => {
    const { context } = useAcmsContext();
    const [searchParams] = useSearchParams();

    const ignoredFilters = useMemo(() => {
      if (sortType === 'entry') {
        return ['user', 'category', 'blog', 'blog_axis', 'category_axis', ...ignoredFiltersProp];
      }
      if (sortType === 'category') {
        return ['user', 'blog', 'blog_axis', 'category_axis', ...ignoredFiltersProp];
      }

      if (sortType === 'user') {
        return ['category', 'blog', 'blog_axis', 'category_axis', ...ignoredFiltersProp];
      }

      return ignoredFiltersProp;
    }, [ignoredFiltersProp, sortType]);

    const [isAdvancedFilterOpen, setIsAdvancedFilterOpen] = useLocalStorage(
      `entry-filter-disclosure-root-${ACMS.Config.root}-suid-${ACMS.Config.suid}-bid-${ACMS.Config.bid}`,
      false
    );

    // eslint-disable-next-line
    const handleAdvancedFilterOpen = useCallback(() => {
      setIsAdvancedFilterOpen((prev) => !prev);
    }, [setIsAdvancedFilterOpen]);

    const filters = useMemo<FilterType[]>(() => {
      const { field } = context;
      if (!field) {
        return [];
      }
      const filters = field.getFields().reduce((acc, field) => {
        return [
          ...acc,
          ...field.filters.map((filter) => ({
            field: field.key,
            operator: filter.operator,
            value: filter.value,
          })),
        ];
      }, [] as FilterType[]);
      return filters;
    }, [context]);

    const _cidInputRef = useRef<HTMLInputElement>(null);
    const handleCategorySelectChange = useCallback((option: CategoryOption | null) => {
      if (_cidInputRef.current) {
        _cidInputRef.current.value = option?.value ?? '';
      }
    }, []);

    const optionalCustomAccessorColumns = columns.filter(
      (column) => isCustomAccessorColumn(column) && ['select', 'checkbox', 'radio'].includes(column.type)
    ) as CustomAccessorColumn<EntryType>[];
    const { optionData } = useOptionDataSWR(optionalCustomAccessorColumns);

    const handleSubmit = useCallback(
      (event: React.FormEvent<HTMLFormElement>) => {
        if (onSubmit) {
          onSubmit(event);
        }
      },
      [onSubmit]
    );

    return (
      <form
        ref={ref}
        id="entry-filter-form"
        action=""
        method="post"
        className="acms-admin-form"
        role="search"
        onSubmit={handleSubmit}
      >
        <Filter>
          <FilterBody>
            <FilterGroupV2>
              <FilterItem id="filter-item-advanced-filter-toggle-button">
                <div>
                  <FilterToggleButton
                    type="button"
                    onClick={handleAdvancedFilterOpen}
                    aria-expanded={isAdvancedFilterOpen}
                    aria-controls="advanced-filter"
                    data-tooltip-id="tooltip-advanced-filter-toggle"
                    data-tooltip-content={ACMS.i18n('entry_index.filter.tooltip.advance')}
                    aria-label={
                      isAdvancedFilterOpen
                        ? ACMS.i18n('entry_index.filter.advance.close')
                        : ACMS.i18n('entry_index.filter.advance.open')
                    }
                    disabled={ignoredFilters.includes('advance')}
                  />
                  <Tooltip id="tooltip-advanced-filter-toggle" place="top" />
                </div>
              </FilterItem>
              {!ignoredFilters.includes('keyword') && (
                <FilterItem id="filter-item-keyword">
                  <FilterFormControl>
                    <FilterItemLabel htmlFor="filter-keyword">
                      {ACMS.i18n('entry_index.filter.keyword')}
                    </FilterItemLabel>
                    <input
                      type="text"
                      name="keyword"
                      defaultValue={context.keyword}
                      key={context.keyword}
                      className="acms-admin-form-width-full"
                      id="filter-keyword"
                      placeholder={ACMS.i18n('entry_index.filter.keyword_placeholder')}
                    />
                  </FilterFormControl>
                </FilterItem>
              )}
              {!ignoredFilters.includes('category') && (
                <FilterItem id="filter-item-category">
                  <HStack spacing="0.25rem" align="end" className="acms-admin-width-max">
                    <FilterFormControl>
                      <FilterItemLabel htmlFor="input-select-category-id">
                        {ACMS.i18n('entry_index.category')}
                      </FilterItemLabel>
                      <CategorySelect
                        noOption
                        inputId="input-select-category-id"
                        name="cid"
                        className="acms-admin-form-width-full"
                        onChange={handleCategorySelectChange}
                        isClearable
                        placeholder={ACMS.i18n('select.not_selected')}
                        defaultValue={searchParams.get('_cid') || context.cid?.toString()}
                        key={`${searchParams.get('_cid')}-${context.cid}`}
                      />
                    </FilterFormControl>
                    {!ignoredFilters.includes('category_axis') && (
                      <div>
                        <input
                          type="checkbox"
                          name="category_axis"
                          defaultValue="descendant-or-self"
                          className="acms-admin-btn-checkbox"
                          id="input-checkbox-axis-descendant-or-self-category"
                          aria-label={ACMS.i18n('entry_index.filter.category_axis.descendant-or-self')}
                          defaultChecked={searchParams.get('category_axis') === 'descendant-or-self'}
                          key={searchParams.get('category_axis')}
                          data-tooltip-id="tooltip-category-axis"
                          data-tooltip-html={nl2br(ACMS.i18n('entry_index.filter.tooltip.category_axis'))}
                        />
                        {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
                        <label
                          htmlFor="input-checkbox-axis-descendant-or-self-category"
                          className="acms-admin-btn-axis"
                          data-tooltip-id="tooltip-category-axis"
                          data-tooltip-html={nl2br(ACMS.i18n('entry_index.filter.tooltip.category_axis'))}
                        >
                          <span className="material-symbols-outlined" aria-hidden="true" />
                        </label>
                        <Tooltip id="tooltip-category-axis" place="top" />
                        <input type="hidden" defaultValue="category_axis" name="query[]" />
                      </div>
                    )}
                  </HStack>
                </FilterItem>
              )}
              {!ignoredFilters.includes('blog') && (
                <FilterItem id="filter-item-blog">
                  <HStack spacing="0.25rem" align="end" className="acms-admin-width-max">
                    <FilterFormControl>
                      <FilterItemLabel htmlFor="filter-_bid">{ACMS.i18n('entry_index.blog')}</FilterItemLabel>
                      <BlogSelect
                        inputId="filter-_bid"
                        name="_bid"
                        className="acms-admin-form-width-full"
                        defaultValue={searchParams.get('_bid') || context.bid?.toString()}
                        key={`${searchParams.get('_bid')}-${context.bid}`}
                      />
                      <input type="hidden" name="query[]" defaultValue="_bid" />
                    </FilterFormControl>
                    {!ignoredFilters.includes('blog_axis') && (
                      <div>
                        <input
                          type="checkbox"
                          name="axis"
                          defaultValue="descendant-or-self"
                          defaultChecked={searchParams.get('axis') === 'descendant-or-self'}
                          key={searchParams.get('axis')}
                          className="acms-admin-btn-checkbox"
                          id="input-checkbox-axis-descendant-or-self-blog"
                          aria-label={ACMS.i18n('entry_index.filter.blog_axis.descendant-or-self')}
                          data-tooltip-id="tooltip-blog-axis"
                          data-tooltip-html={nl2br(ACMS.i18n('entry_index.filter.tooltip.blog_axis'))}
                        />
                        {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
                        <label
                          htmlFor="input-checkbox-axis-descendant-or-self-blog"
                          className="acms-admin-btn-axis"
                          data-tooltip-id="tooltip-blog-axis"
                          data-tooltip-html={nl2br(ACMS.i18n('entry_index.filter.tooltip.blog_axis'))}
                        >
                          <span className="material-symbols-outlined" aria-hidden="true" />
                        </label>
                        <Tooltip id="tooltip-blog-axis" place="top" />
                        <input type="hidden" defaultValue="axis" name="query[]" />
                      </div>
                    )}
                  </HStack>
                </FilterItem>
              )}
              {!ignoredFilters.includes('status') && (
                <FilterItem id="filter-item-status">
                  <FilterFormControl>
                    <FilterItemLabel htmlFor="filter-status">{ACMS.i18n('entry_index.filter.status')}</FilterItemLabel>
                    <RichSelect<(typeof statusOptions)[number]>
                      inputId="filter-status"
                      name="status"
                      className="acms-admin-form-width-full"
                      defaultValue={statusOptions.find((option) => option.value === searchParams.get('status'))}
                      isClearable
                      placeholder={ACMS.i18n('select.not_selected')}
                      key={searchParams.get('status')}
                      options={statusOptions}
                    />
                    <input type="hidden" name="query[]" defaultValue="status" />
                  </FilterFormControl>
                </FilterItem>
              )}
              {!ignoredFilters.includes('user') && (
                <FilterItem id="filter-item-user">
                  <FilterFormControl>
                    <FilterItemLabel htmlFor="filter-uid">{ACMS.i18n('entry_index.filter.user')}</FilterItemLabel>
                    <UserSelect
                      inputId="filter-uid"
                      name="uid"
                      className="acms-admin-form-width-full"
                      defaultValue={context.uid?.toString() || undefined}
                      placeholder={ACMS.i18n('select.not_selected')}
                      key={context.uid}
                    />
                  </FilterFormControl>
                </FilterItem>
              )}
            </FilterGroupV2>

            {!ignoredFilters.includes('advance') && isAdvancedFilterOpen && (
              <FilterInner id="advanced-filter">
                <FilterGroupV2>
                  <h2 className="acms-admin-h6 acms-admin-margin-bottom-none">
                    {ACMS.i18n('entry_index.filter.advance.title')}
                  </h2>
                </FilterGroupV2>
                <DataviewFilter<EntryType>
                  columns={columns}
                  optionData={optionData}
                  filters={filters}
                  filterColumns={(column) => {
                    if (isCustomAccessorColumn(column)) {
                      return true;
                    }
                    if (FILTERABLE_ENTRY_COLUMN_IDS.some((id) => id === column.id)) {
                      return true;
                    }
                    return false;
                  }}
                />
              </FilterInner>
            )}
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
        {!ignoredFilters.includes('category') && (
          <>
            {/* ↓カテゴリーなしで絞り込めるようにするために必要 */}
            <input
              ref={_cidInputRef}
              type="hidden"
              name="_cid"
              defaultValue={searchParams.get('_cid') ?? undefined}
              key={searchParams.get('_cid')}
            />
            <input type="hidden" name="query[]" defaultValue="_cid" />
          </>
        )}
        <input type="hidden" name="bid" defaultValue={context.bid} key={context.bid} />
        <input type="hidden" name="order" defaultValue={context.order} key={context.order} />
        <input type="hidden" name="admin" defaultValue={context.admin || 'entry_index'} key={context.admin} />
      </form>
    );
  }
);

EntryFilter.displayName = 'EntryFilter';

export default EntryFilter;
