import classnames from 'classnames';
import RangeSlider from 'rc-slider';
import dayjs from 'dayjs';
import styled from 'styled-components';
import { AxiosResponse } from 'axios';
import 'rc-slider/assets/index.css';

import { Component, createRef } from 'react';
import { Grid, GridItem } from '@components/grid';
import { MediaItem, MediaAjaxConfig, MediaViewFileType, MediaTag } from '../../types';
import axiosLib from '../../../../lib/axios';
import { formatBytes, parseQuery } from '../../../../utils';
import {
  Menu,
  MenuList,
  MenuTrigger,
  MenuDivider,
  MenuItemCheckbox,
  MenuItemRadioGroup,
  MenuItemRadio,
  MenuPopover,
} from '../../../../components/dropdown-menu';
import { Pagination, PaginationRoot, PaginationSummary } from '../../../../components/pagination/pagination';
import {
  Filter,
  FilterBody,
  FilterContent,
  FilterDetailButton,
  FilterFooter,
  FilterGroup,
  FilterGroupTitle,
  FilterInner,
  FilterItem,
  FilterItemLabel,
} from '../../../../components/filter';
import * as actions from '../../stores/actions';
import ConditionalWrap from '../../../../components/conditional-wrap/conditional-wrap';
import RichSelect from '../../../../components/rich-select/rich-select';
import CreatableSelect from '../../../../components/rich-select/creatable-select';
import { notify } from '../../../../lib/notify';
import VisuallyHidden from '../../../../components/visually-hidden';
import { pending } from '../../../../lib/pending';

/* eslint react/default-props-match-prop-types: 0 */
/* eslint camelcase: 0 */

interface MediaListProps {
  actions: typeof actions;
  items: MediaItem[];
  archives: string[];
  tags: string[];
  extensions?: string[];
  selectedTags: string[];
  config: Required<MediaAjaxConfig>;
  lastPage: number;
  mode: string;
  total: number;
  filetype?: 'all' | 'image' | 'file';
  radioMode?: boolean;
}

interface Menu {
  image: boolean;
  id: boolean;
  filename: boolean;
  tag: boolean;
  last_modified: boolean;
  upload_datetime: boolean;
  imagesize: boolean;
  filesize: boolean;
  blogname: boolean;
}

type SortDirection = 'asc' | 'desc';

interface OrderActive {
  media_title: SortDirection;
  media_last_modified: SortDirection;
  media_datetime?: SortDirection;
  media_path: SortDirection;
  media_size: SortDirection;
  media_filesize: SortDirection;
}

type DisplayStyle = 'table' | 'list';

interface MediaListState {
  order: OrderActive;
  menu: Menu;
  scale: number;
  filterMenuOpened: boolean;
  editMode: string;
  year: string;
  month: string;
  toggleAll: boolean;
  orderActive: keyof OrderActive;
  search: string;
  labels: MediaTag[];
  selectedLabels: MediaTag[];
  style: DisplayStyle;
  oldItemId: string | null;
  items: MediaItem[];
  limit: number;
  displayPage: number;
  filteredOptions: MediaTag[];
  fileext: string;
  owner: boolean;
  blogAxis: boolean;
  filetype: MediaViewFileType;
}

const delimiter = ',';

const StyledMediaList = styled.div`
  .acms-admin-inline-block {
    vertical-align: middle;
  }
`;

export default class MediaList extends Component<MediaListProps, MediaListState> {
  listRef: React.RefObject<HTMLFormElement>;

  static defaultProps = {
    radioMode: false,
    filetype: 'all',
    extensions: [],
  };

  constructor(props: MediaListProps) {
    super(props);
    this.state = {
      order: {
        media_title: 'asc',
        media_last_modified: 'desc',
        media_path: 'asc',
        media_size: 'asc',
        media_filesize: 'asc',
      },
      menu: {
        image: true,
        id: true,
        filename: true,
        tag: true,
        last_modified: true,
        upload_datetime: false,
        imagesize: true,
        filesize: true,
        blogname: false,
      },
      limit: 20,
      displayPage: 6,
      scale: 1,
      selectedLabels: [],
      year: '',
      month: '',
      filterMenuOpened: false,
      toggleAll: false,
      orderActive: 'media_last_modified',
      search: '',
      labels: [],
      items: props.items,
      filteredOptions: [],
      style: 'table',
      editMode: '',
      filetype: props.filetype ? props.filetype : 'all',
      fileext: 'all',
      owner: false,
      blogAxis: false,
      oldItemId: null,
    };
    this.listRef = createRef<HTMLFormElement>();
  }

  getOrderSelectBtnLabel() {
    const { orderActive, order } = this.state;
    const active = order[orderActive] === 'asc' ? ACMS.i18n('media.asc') : ACMS.i18n('media.desc');
    if (orderActive === 'media_title') {
      return `${ACMS.i18n('media.title')}（${active}）`;
    }
    if (orderActive === 'media_last_modified') {
      return `${ACMS.i18n('media.update_date')}（${active}）`;
    }
    if (orderActive === 'media_datetime') {
      return `${ACMS.i18n('media.created_date')}（${active}）`;
    }
    if (orderActive === 'media_path') {
      return `${ACMS.i18n('media.file_name')}（${active}）`;
    }
    if (orderActive === 'media_filesize') {
      return `${ACMS.i18n('media.file_size')}（${active}）`;
    }
    return ACMS.i18n('media.select');
  }

  setItem(item: MediaItem) {
    const { media_id } = item;
    const url = ACMS.Library.acmsLink(
      {
        tpl: 'ajax/edit/media-edit.json',
        bid: ACMS.Config.bid,
        Query: {
          cache: new Date().getTime().toString(),
        },
      },
      false
    );
    if ('history' in window) {
      history.replaceState(null, '', `#mid=${media_id}`);
    }
    axiosLib
      .get(url, {
        params: {
          _mid: media_id,
        },
      })
      .then((res) => {
        if (res.data.item && res.data.item.media_id) {
          this.props.actions.setItem(res.data.item as MediaItem);
        }
      });
    return false;
  }

  toggleCheck(item: MediaItem) {
    const { items, radioMode } = this.props;
    if (radioMode) {
      const findItem = items.find((item) => item.checked);
      if (findItem) {
        this.props.actions.updateMediaList({ ...findItem, checked: !findItem.checked });
      }
    }
    this.props.actions.updateMediaList({ ...item, checked: !item.checked });
  }

  toggleCheckFrom(fromItemId: string, toItem: MediaItem) {
    const { items, radioMode } = this.props;
    const { updateMediaList } = this.props.actions;
    if (radioMode) {
      return;
    }
    const fromIndex = items.findIndex((item) => {
      if (item.media_id === fromItemId) {
        return true;
      }
      return false;
    });
    const toIndex = items.findIndex((item) => {
      if (item.media_id === toItem.media_id) {
        return true;
      }
      return false;
    });
    const { checked } = toItem;
    const nextChecked = !checked;
    const minIndex = fromIndex > toIndex ? toIndex : fromIndex;
    const maxIndex = fromIndex > toIndex ? fromIndex : toIndex;
    items.forEach((item, index) => {
      if (index >= minIndex && index <= maxIndex) {
        updateMediaList({ ...item, checked: nextChecked });
      }
    });
  }

  toggleAllCheck() {
    const { items, actions, radioMode } = this.props;
    const { toggleAll } = this.state;
    const all = !toggleAll;
    this.setState({
      toggleAll: all,
    });
    if (radioMode) {
      return;
    }
    if (all) {
      actions.setMediaList(items.map((item) => ({ ...item, checked: true })));
    } else {
      actions.setMediaList(items.map((item) => ({ ...item, checked: false })));
    }
  }

  async removeImgs() {
    if (!confirm(ACMS.i18n('media.remove_media_confirm'))) {
      return;
    }
    if (this.listRef.current === null) {
      return;
    }
    const fd = new FormData(this.listRef.current);
    fd.append('ACMS_POST_Media_Index_Delete', 'true');
    fd.append('formToken', window.csrfToken);
    const end = pending.splash(ACMS.i18n('media.deleting_media'));
    try {
      await $.ajax({
        url: ACMS.Library.acmsLink({ bid: ACMS.Config.bid }),
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
      });
      notify.info(ACMS.i18n('media.remove_media_success'));
      this.fetchMediaList();
    } catch {
      notify.danger(ACMS.i18n('media.remove_media_error'));
    } finally {
      end();
    }
  }

  setOrder(target: keyof OrderActive) {
    const { state } = this;
    const { order } = state;
    const sortDirection = this.getNextSortDirection(target);
    this.setState(
      {
        ...state,
        orderActive: target,
        order: {
          ...order,
          [target]: sortDirection,
        },
      },
      () => {
        this.fetchMediaList();
      }
    );
    // upload_date-desc
  }

  checkOrder(orderActive: keyof OrderActive) {
    this.setState(
      {
        orderActive,
      },
      () => {
        this.fetchMediaList();
      }
    );
  }

  checkAscDesc(ascdesc: 'asc' | 'desc') {
    const { order, orderActive } = this.state;
    order[orderActive] = ascdesc;
    this.setState(
      {
        order,
      },
      () => {
        this.fetchMediaList();
      }
    );
  }

  getOrder() {
    const { orderActive, order } = this.state;
    if (orderActive === 'media_title') {
      return `file_name-${order[orderActive]}`;
    }
    if (orderActive === 'media_last_modified') {
      return `last_modified-${order[orderActive]}`;
    }
    if (orderActive === 'media_datetime') {
      return `upload_date-${order[orderActive]}`;
    }
    if (orderActive === 'media_filesize') {
      return `file_size-${order[orderActive]}`;
    }
    return '';
  }

  fetchMediaList(config: MediaAjaxConfig = {}) {
    const { year, month, limit, filetype, fileext, owner, blogAxis } = this.state;
    const { actions, selectedTags: labels } = this.props;
    const order = this.getOrder();
    const tag = labels.reduce((accumulator, current) => {
      if (!accumulator) {
        return current;
      }
      return `${accumulator}/${current}`;
    }, '');
    const override: MediaAjaxConfig = {
      order,
      tag,
      limit,
      filetype,
      fileext,
      owner,
      blogAxis,
    };
    if (year && month) {
      override.date = `${year}/${month}`;
    } else {
      override.date = '';
      override.year = year;
      override.month = month;
    }
    if (!config.page) {
      config.page = 1;
    }
    const settings = { ...config, ...override } as MediaAjaxConfig;
    if (actions && actions.fetchMediaList) {
      actions.fetchMediaList(settings);
    }
  }

  getIsSorted(th: keyof OrderActive): false | SortDirection {
    const { order, orderActive } = this.state;
    if (orderActive === th) {
      if (order[orderActive] === 'asc') {
        return 'asc';
      }
      return 'desc';
    }
    return false;
  }

  getNextSortDirection(th: keyof OrderActive): SortDirection {
    const { order } = this.state;
    return order[th] === 'asc' ? 'desc' : 'asc';
  }

  changeStyle(style: DisplayStyle) {
    this.setState({
      style,
    });
  }

  getCheckedItemsLength() {
    const { items } = this.props;
    const checks = items.filter((item) => item.checked);
    if (checks && checks.length) {
      return checks.length;
    }
    return 0;
  }

  onLabelChange(labels: readonly MediaTag[]) {
    const tags = labels.map((label) => label.value);
    this.props.actions.setSelectedTags(tags);
    this.getFilteredOptions(tags);
  }

  getFilteredOptions(tags: string[]) {
    axiosLib({
      method: 'GET',
      url: ACMS.Library.acmsLink(
        {
          tpl: 'ajax/edit/media-tag.json',
          bid: ACMS.Config.bid,
          Query: {
            cache: new Date().getTime().toString(),
          },
        },
        false
      ),
      responseType: 'json',
      params: {
        tags,
      },
    }).then((response: AxiosResponse<string[]>) => {
      const { data } = response;
      const filteredOptions = data.map((item) => ({
        value: item,
        label: item,
      }));
      this.setState({
        filteredOptions,
      });
    });
  }

  onChangeDisplayNumber(e: React.ChangeEvent<HTMLSelectElement>) {
    const { actions } = this.props;
    const limit = parseInt(e.target.value, 10);
    actions.setMediaConfig({
      limit,
      page: 1,
    });
    this.setState({ limit });
  }

  onKeywordInput(e: React.FormEvent<HTMLInputElement>) {
    if (!(e.target instanceof HTMLInputElement)) {
      return;
    }
    const { actions } = this.props;
    actions.setMediaConfig({
      keyword: e.target.value,
    });
  }

  getArchives(archives: string[]) {
    let years: string[] = [];
    let months: string[] = [];
    archives.forEach((archive) => {
      const [year, month] = archive.split('-');
      years.push(year);
      months.push(month);
    });
    years = [...new Set(years)].sort();
    months = [...new Set(months)].sort();
    return { years, months };
  }

  onDateChange(value: string, type: 'year' | 'month') {
    if (type === 'month') {
      this.setState({
        month: value,
      });
    } else {
      this.setState({
        year: value,
      });
    }
  }

  onAddedLabelsChange(labels: readonly MediaTag[]) {
    this.setState({
      selectedLabels: labels.map((label) => label),
    });
  }

  async addLabelToItems() {
    if (this.listRef.current === null) {
      return;
    }
    const { selectedLabels } = this.state;
    const tags = selectedLabels.map((selectedLabel) => selectedLabel.value).join(',');
    const fd = new FormData(this.listRef.current);
    fd.append('tags', tags);
    fd.append('ACMS_POST_Media_Index_Tags', 'true');
    fd.append('formToken', window.csrfToken);
    try {
      await $.ajax({
        url: ACMS.Library.acmsLink({ bid: ACMS.Config.bid }),
        data: fd,
        type: 'POST',
        processData: false,
        contentType: false,
      });
      notify.info(ACMS.i18n('media.add_tags_to_media_success'));
      this.fetchMediaList();
    } catch {
      notify.danger(ACMS.i18n('media.add_tags_to_media_error'));
    }
  }

  getFileStyle = (item: MediaItem): React.CSSProperties => {
    if (!item) {
      return {};
    }
    if (item.media_ext.toLowerCase() === 'pdf') {
      return {
        height: '70%',
        width: 'auto',
        opacity: 1,
      };
    }
    return {
      verticalAlign: 'middle',
      width: '70px',
      opacity: 1,
    };
  };

  getImageStyle = (item: MediaItem): React.CSSProperties => {
    if (!item || !item.width || !item.height) {
      return {};
    }
    if (item.width > item.height) {
      return {
        width: '100%',
        height: 'auto',
        opacity: 1,
        transform: `translate(-50%,-50%) scale(${item.width / item.height})`,
      };
    }
    return {
      width: 'auto',
      height: '100%',
      opacity: 1,
      transform: `translate(-50%,-50%) scale(${item.height / item.width})`,
    };
  };

  rowClick(e: React.MouseEvent<HTMLTableRowElement>, item: MediaItem) {
    const { oldItemId } = this.state;
    const element = e.target as HTMLElement;
    if (element.tagName === 'INPUT' || element.tagName === 'I' || element.tagName === 'LABEL') {
      e.preventDefault();
    }
    if (element.tagName !== 'BUTTON') {
      if (e.shiftKey && oldItemId !== null) {
        this.toggleCheckFrom(oldItemId, item);
      } else {
        this.toggleCheck(item);
        this.setState({
          oldItemId: item.media_id,
        });
      }
    }
  }

  componentDidUpdate(prevProps: MediaListProps) {
    const { style, scale, order, orderActive, limit, filterMenuOpened, owner, blogAxis } = this.state;
    localStorage.setItem(
      'acms-media-list',
      JSON.stringify({
        style,
        scale,
        order,
        orderActive,
        limit,
        filterMenuOpened,
        owner,
        blogAxis,
      })
    );

    if (prevProps.items !== this.props.items) {
      this.setItems(this.props.items);
    }
  }

  componentDidMount() {
    const storage = localStorage.getItem('acms-media-list');
    const { selectedTags } = this.props;
    if (!storage) {
      this.fetchMediaList();
      return;
    }
    if (location.hash) {
      const { mid } = parseQuery(location.hash.slice(1));
      if (mid) {
        // @ts-expect-error 対応難易度が高いため、後で対応する
        this.setItem({ media_id: mid });
      }
    }
    const { style, scale, order, orderActive, limit, filterMenuOpened, owner, blogAxis } = JSON.parse(storage);
    this.setState(
      {
        style,
        scale,
        order,
        orderActive,
        limit,
        filterMenuOpened,
        owner,
        blogAxis,
      },
      () => {
        this.fetchMediaList();
      }
    );
    if (selectedTags) {
      this.getFilteredOptions(selectedTags);
    }
  }

  loadImg(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        resolve(img);
      };
      setTimeout(() => {
        resolve(img);
      }, 500);
      img.src = url;
    });
  }

  setItems(items: MediaItem[]) {
    const newItems = items.map(async (item) => {
      if ((item.media_type === 'image' || item.media_type === 'svg') && !item.width) {
        const img = await this.loadImg(item.media_thumbnail);
        if (img.naturalWidth && img.naturalHeight) {
          return { ...item, width: img.naturalWidth, height: img.naturalHeight };
        }
        return { ...item, width: img.width, height: img.height };
      }
      return item;
    });
    Promise.all(newItems).then((items) => {
      this.setState({ items });
    });
  }

  scaleChanged = (scale: number | number[]) => {
    const newScale = Array.isArray(scale) ? scale[0] : scale;
    this.setState({ scale: newScale });
  };

  getOptions = () => {
    const { filteredOptions } = this.state;
    const { tags, selectedTags } = this.props;
    if (selectedTags.length) {
      return filteredOptions;
    }
    return tags.map((tag) => ({
      value: tag,
      label: tag,
    }));
  };

  toggleMenu(menuItem: keyof Menu) {
    const { menu } = this.state;
    const newMenu = { ...menu, [menuItem]: !menu[menuItem] };
    this.setState({
      menu: newMenu,
    });
  }

  render() {
    const { mode, config, archives, tags, total, items, selectedTags, extensions } = this.props;

    const { years, months } = this.getArchives(archives);

    const { page, keyword } = config;
    const {
      style,
      editMode,
      scale,
      selectedLabels,
      items: stateItems,
      limit,
      menu,
      owner,
      blogAxis,
      order,
      orderActive,
      oldItemId,
      toggleAll,
      filterMenuOpened,
      filetype,
    } = this.state;

    const checkedLength = this.getCheckedItemsLength();

    return (
      <StyledMediaList>
        <div id="media-filter-form" role="search" className="acms-admin-form">
          <Filter>
            <FilterBody>
              <FilterGroup>
                <FilterGroupTitle>{ACMS.i18n('media.filter_search')}</FilterGroupTitle>
                <FilterContent fit>
                  <FilterItem>
                    <FilterItemLabel htmlFor="filter-date">{ACMS.i18n('media.created_date')}</FilterItemLabel>

                    <div style={{ display: 'flex' }}>
                      <div className="acms-admin-inline-block acms-admin-margin-right-mini">
                        <select
                          id="filter-date"
                          className="acms-admin-margin-right-mini"
                          onChange={(e) => {
                            this.onDateChange(e.target.value, 'year');
                          }}
                        >
                          <option value="">{ACMS.i18n('media.year')}</option>
                          {years.map((year) => (
                            <option value={year} key={year}>
                              {year}
                            </option>
                          ))}
                        </select>
                      </div>
                      <VisuallyHidden asChild>
                        <label htmlFor="filter-date-month">{ACMS.i18n('media.month')}</label>
                      </VisuallyHidden>
                      <div className="acms-admin-inline-block">
                        <select
                          id="filter-date-month"
                          onChange={(e) => {
                            this.onDateChange(e.target.value, 'month');
                          }}
                        >
                          <option value="">{ACMS.i18n('media.month')}</option>
                          {months.map((month) => (
                            <option value={month} key={month}>
                              {month}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </FilterItem>

                  <FilterItem full>
                    <FilterItemLabel htmlFor="filter-tag">{ACMS.i18n('media.tag')}</FilterItemLabel>
                    <div className="acms-admin-form-width-medium">
                      <RichSelect<MediaTag, true>
                        isMulti
                        value={selectedTags.map((selectedTag) => ({
                          label: selectedTag,
                          value: selectedTag,
                        }))}
                        options={this.getOptions()}
                        onChange={this.onLabelChange.bind(this)}
                        closeMenuOnSelect={false}
                        placeholder={ACMS.i18n('media.tag_search_select_placeholder')}
                        noOptionsMessage={() => ACMS.i18n('media.tag_select_no_options_message')}
                        inputId="filter-tag"
                      />
                    </div>
                  </FilterItem>
                </FilterContent>
              </FilterGroup>

              {filterMenuOpened && (
                <div id="search-detail">
                  <FilterInner>
                    <FilterGroup>
                      <FilterGroupTitle>{ACMS.i18n('media.detailed_condition')}</FilterGroupTitle>
                      <FilterContent>
                        <FilterItem full>
                          <FilterItemLabel htmlFor="filter-keyword">{ACMS.i18n('media.keyword')}</FilterItemLabel>
                          <input
                            type="text"
                            placeholder={ACMS.i18n('media.search_by_keyword')}
                            id="filter-keyword"
                            className="acms-admin-form-width-full"
                            value={keyword}
                            onInput={this.onKeywordInput.bind(this)}
                          />
                        </FilterItem>
                      </FilterContent>
                    </FilterGroup>
                    <FilterGroup>
                      <FilterGroupTitle>{ACMS.i18n('media.narrow_down')}</FilterGroupTitle>
                      <FilterContent>
                        <FilterItem>
                          <FilterItemLabel htmlFor="filter-sort">{ACMS.i18n('media.number_of_items')}</FilterItemLabel>
                          <select id="filter-sort" value={limit} onChange={this.onChangeDisplayNumber.bind(this)}>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="75">75</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="300">300</option>
                          </select>
                        </FilterItem>
                        <FilterItem>
                          <FilterItemLabel htmlFor="filter-type">{ACMS.i18n('media.type')}</FilterItemLabel>
                          <select
                            id="filter-type"
                            disabled={this.props.filetype !== 'all'}
                            defaultValue={filetype}
                            onChange={(e) => {
                              this.setState({
                                filetype: e.target.value as MediaViewFileType,
                              });
                            }}
                          >
                            <option value="all">{ACMS.i18n('media.all')}</option>
                            <option value="image">{ACMS.i18n('media.image')}</option>
                            <option value="file">{ACMS.i18n('media.file')}</option>
                          </select>
                        </FilterItem>
                        <FilterItem>
                          <FilterItemLabel htmlFor="filter-ext">{ACMS.i18n('media.extension')}</FilterItemLabel>
                          <select
                            id="filter-ext"
                            onChange={(e) => {
                              this.setState({
                                fileext: e.target.value,
                              });
                            }}
                          >
                            <option value="all">{ACMS.i18n('media.all')}</option>
                            {extensions &&
                              extensions.map((extension) => (
                                <option value={extension} key={extension.toLowerCase()}>
                                  {extension.toLowerCase()}
                                </option>
                              ))}
                          </select>
                        </FilterItem>
                        <FilterItem>
                          <label htmlFor="filter-owner" className="acms-admin-form-checkbox acms-admin-m-0">
                            <input
                              id="filter-owner"
                              type="checkbox"
                              checked={owner}
                              onChange={(e) => {
                                this.setState({
                                  owner: e.target.checked,
                                });
                              }}
                            />
                            <i className="acms-admin-ico-checkbox" />
                            {ACMS.i18n('media.owner')}
                          </label>
                        </FilterItem>
                        {ACMS.Config.sbid !== ACMS.Config.bid && (
                          <FilterItem>
                            <label htmlFor="filter-blog-axis" className="acms-admin-form-checkbox acms-admin-m-0">
                              <input
                                id="filter-blog-axis"
                                type="checkbox"
                                checked={blogAxis}
                                onChange={(e) => {
                                  this.setState({
                                    blogAxis: e.target.checked,
                                  });
                                }}
                              />
                              <i className="acms-admin-ico-checkbox" />
                              {ACMS.i18n('media.blog_axis')}
                            </label>
                          </FilterItem>
                        )}
                      </FilterContent>
                    </FilterGroup>
                  </FilterInner>
                </div>
              )}
            </FilterBody>
            <FilterFooter>
              <button
                type="button"
                className="acms-admin-btn acms-admin-btn-info acms-admin-btn-search"
                onClick={() => {
                  this.fetchMediaList();
                }}
              >
                {ACMS.i18n('media.search')}
              </button>

              <FilterDetailButton
                type="button"
                onClick={(e) => {
                  e.preventDefault();
                  this.setState({
                    filterMenuOpened: !filterMenuOpened,
                  });
                }}
                aria-expanded={filterMenuOpened}
                aria-controls="search-detail"
              >
                {ACMS.i18n('media.detailed_search')}
              </FilterDetailButton>
            </FilterFooter>
          </Filter>
        </div>

        <div className="acms-admin-media-action-group">
          <div className="acms-admin-media-action-item acms-admin-media-action-item-fix">
            <div className="acms-admin-btn-group acms-admin-media-action-display-switch" role="group">
              <button
                type="button"
                className={classnames('acms-admin-btn', {
                  'acms-admin-btn-active': style === 'table',
                })}
                onClick={this.changeStyle.bind(this, 'table')}
                aria-pressed={style === 'table'}
                aria-label={ACMS.i18n('media.display_switch_list')}
              >
                <i className="acms-admin-icon-list" />
              </button>
              <button
                type="button"
                className={classnames('acms-admin-btn', {
                  'acms-admin-btn-active': style === 'list',
                })}
                onClick={this.changeStyle.bind(this, 'list')}
                aria-pressed={style === 'list'}
                aria-label={ACMS.i18n('media.display_switch_grid')}
              >
                <i className="acms-admin-icon-grid" />
              </button>
            </div>

            <div className="acms-admin-media-action-sort-select-wrap">
              <div className="acms-admin-media-action-sort-select">
                {style === 'table' && (
                  <Menu closeOnSelect={false}>
                    <MenuTrigger className="acms-admin-btn" aria-label={ACMS.i18n('media.open_config_menu')}>
                      <i className="acms-admin-icon-config" aria-hidden="true" />
                    </MenuTrigger>
                    <MenuPopover>
                      <MenuList>
                        <MenuItemCheckbox
                          value="image"
                          checked={menu.image}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.image2')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="id"
                          checked={menu.id}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          ID
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="filename"
                          checked={menu.filename}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.file_name')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="tag"
                          checked={menu.tag}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.tag')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="last_modified"
                          checked={menu.last_modified}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.updated_date')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="upload_datetime"
                          checked={menu.upload_datetime}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.created_date')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="filesize"
                          checked={menu.filesize}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.file_size')}
                        </MenuItemCheckbox>
                        <MenuItemCheckbox
                          value="blogname"
                          checked={menu.blogname}
                          onCheckedChange={(event) => {
                            this.toggleMenu(event.detail.value as keyof Menu);
                          }}
                        >
                          {ACMS.i18n('media.blog_name')}
                        </MenuItemCheckbox>
                      </MenuList>
                    </MenuPopover>
                  </Menu>
                )}
              </div>

              <div className="acms-admin-media-action-sort-select" style={{ position: 'relative' }}>
                <Menu closeOnSelect={false}>
                  <MenuTrigger type="button" className="acms-admin-select-btn">
                    {this.getOrderSelectBtnLabel()}
                  </MenuTrigger>
                  <MenuPopover>
                    <MenuList>
                      <MenuItemRadioGroup
                        value={orderActive}
                        onValueChange={(event) => {
                          this.checkOrder(event.detail.value as keyof OrderActive);
                        }}
                      >
                        <MenuItemRadio value="media_title">{ACMS.i18n('media.title')}</MenuItemRadio>
                        <MenuItemRadio value="media_last_modified">{ACMS.i18n('media.updated_date')}</MenuItemRadio>
                        <MenuItemRadio value="media_datetime">{ACMS.i18n('media.created_date')}</MenuItemRadio>
                        <MenuItemRadio value="media_filesize">{ACMS.i18n('media.file_size')}</MenuItemRadio>
                      </MenuItemRadioGroup>
                      <MenuDivider />
                      <MenuItemRadioGroup
                        value={order[orderActive]}
                        onValueChange={(event) => {
                          this.checkAscDesc(event.detail.value as SortDirection);
                        }}
                      >
                        <MenuItemRadio value="asc">{ACMS.i18n('media.asc')}</MenuItemRadio>
                        <MenuItemRadio value="desc">{ACMS.i18n('media.desc')}</MenuItemRadio>
                      </MenuItemRadioGroup>
                    </MenuList>
                  </MenuPopover>
                </Menu>
              </div>
            </div>
          </div>

          <div className="acms-admin-media-action-item acms-admin-media-action-item-middle">
            {style === 'list' && (
              <div className="acms-admin-media-action-range">
                <span style={{ display: 'inline-flex', width: '100%', alignItems: 'center' }}>
                  <VisuallyHidden>{ACMS.i18n('media.display_size')}</VisuallyHidden>
                  <i
                    className="acms-admin-icon-unit-image"
                    style={{ fontSize: '12px', color: '#777777', marginRight: '10px' }}
                  />
                  <RangeSlider
                    className="acms-admin-range-slider"
                    min={1}
                    max={2}
                    step={0.1}
                    value={scale}
                    onChange={this.scaleChanged}
                  />
                  <i
                    className="acms-admin-icon-unit-image"
                    style={{ fontSize: '18px', color: '#777777', marginLeft: '10px' }}
                  />
                </span>
              </div>
            )}

            {style === 'list' && (
              <div className="acms-admin-media-action-select-all">
                <button
                  type="button"
                  onClick={this.toggleAllCheck.bind(this)}
                  className="acms-admin-media-grid-checkbox-wrap"
                >
                  <span
                    className={classnames('acms-admin-media-grid-checkbox', {
                      selected: toggleAll,
                    })}
                    aria-hidden="true"
                  />
                  {ACMS.i18n('media.select_all')}
                </button>
              </div>
            )}
          </div>

          <div className="acms-admin-form-group acms-admin-media-action-item acms-admin-media-action-item-left">
            <div className="acms-admin-form">
              <VisuallyHidden asChild>
                <label htmlFor="media-action-select">{ACMS.i18n('media.select')}</label>
              </VisuallyHidden>
              <select
                id="media-action-select"
                className="acms-admin-margin-right-mini"
                value={editMode}
                onChange={(e) => {
                  const { value } = e.target;
                  this.setState({
                    editMode: value,
                  });
                }}
              >
                <option>{ACMS.i18n('media.select')}</option>
                <option value="remove">{ACMS.i18n('media.remove')}</option>
                <option value="tag">{ACMS.i18n('media.add_tags')}</option>
              </select>
              {editMode === 'remove' && (
                <>
                  {ACMS.i18n('media.checked_items').replace('$1', checkedLength.toString())}
                  &nbsp;
                  <button
                    type="button"
                    className="acms-admin-btn acms-admin-btn-danger"
                    onClick={this.removeImgs.bind(this)}
                  >
                    {ACMS.i18n('media.remove')}
                  </button>
                </>
              )}
              {editMode === 'tag' && (
                <>
                  <div
                    style={{ display: 'inline-block', verticalAlign: 'middle' }}
                    className="acms-admin-margin-right-mini"
                  >
                    {ACMS.i18n('media.add_tags_to_checked_items').replace('$1', checkedLength.toString())}
                  </div>
                  <div
                    style={{
                      display: 'inline-block',
                      width: '200px',
                      verticalAlign: 'middle',
                      marginRight: '5px',
                    }}
                  >
                    <CreatableSelect<MediaTag, true>
                      isMulti
                      onChange={this.onAddedLabelsChange.bind(this)}
                      value={selectedLabels}
                      options={tags.map((tag) => ({
                        label: tag,
                        value: tag,
                      }))}
                      placeholder={ACMS.i18n('media.tag_select_placeholder')}
                      noOptionsMessage={() => ACMS.i18n('media.tag_select_no_options_message')}
                      formatCreateLabel={(inputValue) => ACMS.i18n('media.add_tag', { name: inputValue })}
                      isValidNewOption={(inputValue) => inputValue.trim().length > 0}
                      closeMenuOnSelect={false}
                    />
                  </div>
                  <button type="button" className="acms-admin-btn" onClick={this.addLabelToItems.bind(this)}>
                    追加
                  </button>
                </>
              )}
            </div>
          </div>

          <form
            ref={this.listRef}
            className="acms-admin-media-list"
            style={{ display: style === 'table' ? 'block' : 'none' }}
          >
            <div className="acms-admin-table-scroll-xs acms-admin-table-scroll-sm acms-admin-table-scroll-md">
              <table className="adminTable acms-admin-table-admin acms-admin-table-hover acms-admin-media-table">
                <thead className="acms-admin-table-heading">
                  <tr>
                    <th className="acms-admin-media-row-check acms-admin-table-nowrap">
                      {mode === 'edit' && (
                        <label // eslint-disable-line jsx-a11y/label-has-associated-control
                          htmlFor="checkAll"
                          className="acms-admin-form-checkbox acms-admin-m-0"
                          onChange={this.toggleAllCheck.bind(this)}
                        >
                          <input id="checkAll" type="checkbox" />
                          <i className="acms-admin-ico-checkbox" />
                        </label>
                      )}
                    </th>
                    {menu.image && (
                      <th className="acms-admin-media-row-image acms-admin-table-nowrap">
                        {ACMS.i18n('media.image2')}
                      </th>
                    )}
                    {menu.id && <th className="acms-admin-media-row-id acms-admin-table-nowrap">ID</th>}
                    {menu.filename && (
                      <th
                        className="acms-admin-media-row-title"
                        aria-sort={
                          this.getIsSorted('media_title')
                            ? `${this.getIsSorted('media_title') as SortDirection}ending`
                            : undefined
                        }
                      >
                        <button
                          type="button"
                          className="acms-admin-th-sort-btn"
                          onClick={this.setOrder.bind(this, 'media_title')}
                          data-next-sort={`${this.getNextSortDirection('media_title')}ending`}
                        >
                          {ACMS.i18n('media.file_name')}
                          <span className="acms-admin-th-sort-btn-icon" />
                          <VisuallyHidden>
                            {ACMS.i18n(`table.label.sort.${this.getIsSorted('media_title') || 'none'}`)}
                          </VisuallyHidden>
                        </button>
                      </th>
                    )}
                    {menu.tag && <th className="acms-admin-media-row-tag">{ACMS.i18n('media.tag')}</th>}
                    {menu.last_modified && (
                      <th
                        className="acms-admin-media-row-date acms-admin-table-nowrap"
                        aria-sort={
                          this.getIsSorted('media_last_modified')
                            ? `${this.getIsSorted('media_last_modified') as SortDirection}ending`
                            : undefined
                        }
                      >
                        <button
                          type="button"
                          className="acms-admin-th-sort-btn"
                          onClick={this.setOrder.bind(this, 'media_last_modified')}
                          data-next-sort={`${this.getNextSortDirection('media_last_modified')}ending`}
                        >
                          {ACMS.i18n('media.updated_date')}
                          <span className="acms-admin-th-sort-btn-icon" />
                          <VisuallyHidden>
                            {ACMS.i18n(`table.label.sort.${this.getIsSorted('media_last_modified') || 'none'}`)}
                          </VisuallyHidden>
                        </button>
                      </th>
                    )}
                    {menu.upload_datetime && (
                      <th
                        className="acms-admin-media-row-date acms-admin-table-nowrap"
                        aria-sort={
                          this.getIsSorted('media_datetime')
                            ? `${this.getIsSorted('media_datetime') as SortDirection}ending`
                            : undefined
                        }
                      >
                        <button
                          type="button"
                          className="acms-admin-th-sort-btn"
                          onClick={this.setOrder.bind(this, 'media_datetime')}
                          data-next-sort={`${this.getNextSortDirection('media_datetime')}ending`}
                        >
                          {ACMS.i18n('media.created_date')}
                          <span className="acms-admin-th-sort-btn-icon" />
                          <VisuallyHidden>
                            {ACMS.i18n(`table.label.sort.${this.getIsSorted('media_datetime') || 'none'}`)}
                          </VisuallyHidden>
                        </button>
                      </th>
                    )}
                    {menu.filesize && (
                      <th
                        className="acms-admin-media-row-size acms-admin-table-nowrap"
                        aria-sort={
                          this.getIsSorted('media_filesize')
                            ? `${this.getIsSorted('media_filesize') as SortDirection}ending`
                            : undefined
                        }
                      >
                        <button
                          type="button"
                          className="acms-admin-th-sort-btn"
                          onClick={this.setOrder.bind(this, 'media_filesize')}
                          data-next-sort={`${this.getNextSortDirection('media_filesize')}ending`}
                        >
                          {ACMS.i18n('media.file_size')}
                          <span className="acms-admin-th-sort-btn-icon" />
                          <VisuallyHidden>
                            {ACMS.i18n(`table.label.sort.${this.getIsSorted('media_filesize') || 'none'}`)}
                          </VisuallyHidden>
                        </button>
                      </th>
                    )}
                    {menu.blogname && <th className="acms-admin-media-row-blogname">{ACMS.i18n('media.blog_name')}</th>}
                    {mode === 'edit' && (
                      <th
                        className="acms-admin-media-row-action acms-admin-table-nowrap"
                        style={{ whiteSpace: 'nowrap' }}
                      >
                        {ACMS.i18n('media.action')}
                      </th>
                    )}
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr
                      key={item.media_id}
                      onClick={(e) => {
                        this.rowClick(e, item);
                      }}
                    >
                      <td className="acms-admin-media-row-check acms-admin-table-nowrap">
                        {item.media_editable}
                        {(mode !== 'edit' || item.media_editable) && (
                          // eslint-disable-next-line jsx-a11y/label-has-associated-control
                          <label className="acms-admin-form-checkbox acms-admin-m-0">
                            <input
                              type="checkbox"
                              name="checks[]"
                              value={`${item.media_bid}:${item.media_id}`}
                              checked={item.checked}
                              onChange={this.toggleCheck.bind(this, item)}
                            />
                            <i className="acms-admin-ico-checkbox" />
                          </label>
                        )}
                      </td>
                      {menu.image && (
                        <td className="acms-admin-media-row-image acms-admin-table-nowrap">
                          <ConditionalWrap
                            // eslint-disable-next-line react/no-unstable-nested-components
                            wrap={(chidren) => (
                              <a href={item.media_permalink} target="_blank" rel="noopener noreferrer">
                                {chidren}
                              </a>
                            )}
                            condition={item.media_type === 'file'}
                          >
                            <div className="acms-admin-media-list-thumbnail-wrap">
                              <img
                                src={`${item.media_thumbnail}?date=${item.media_last_modified}`}
                                className="acms-admin-media-list-thumbnail"
                                alt=""
                              />
                            </div>
                          </ConditionalWrap>
                        </td>
                      )}
                      {menu.id && (
                        <td className="acms-admin-media-row-id acms-admin-table-nowrap">
                          <span className="acms-admin-label">{item.media_id}</span>
                        </td>
                      )}
                      {menu.filename && <td className="acms-admin-media-row-title">{item.media_title}</td>}
                      {menu.tag && (
                        <td className="acms-admin-media-row-tag">
                          {item.media_label &&
                            item.media_label.split(delimiter).map((label) => (
                              <span
                                className="acms-admin-label acms-admin-label-info"
                                style={{
                                  display: 'inline-block',
                                  marginRight: '5px',
                                  marginTop: '5px',
                                }}
                                key={label}
                              >
                                {label}
                              </span>
                            ))}
                        </td>
                      )}
                      {menu.last_modified && (
                        <td className="acms-admin-media-row-date acms-admin-table-nowrap">
                          {dayjs(item.media_last_modified).format('YYYY/MM/DD')}
                          <br />
                          {dayjs(item.media_last_modified).format('HH:mm')}
                        </td>
                      )}
                      {menu.upload_datetime && (
                        <td className="acms-admin-media-row-date acms-admin-table-nowrap">
                          {dayjs(item.media_datetime).format('YYYY/MM/DD')}
                          <br />
                          {dayjs(item.media_datetime).format('HH:mm')}
                        </td>
                      )}
                      {menu.filesize && (
                        <td className="acms-admin-media-row-action acms-admin-table-nowrap">
                          {item.media_filesize && <>{formatBytes(item.media_filesize)}</>}
                          {item.media_size && item.media_type === 'image' && (
                            <span style={{ display: 'block', marginTop: '5px' }}>
                              {item.media_size}
                              px
                            </span>
                          )}
                        </td>
                      )}
                      {menu.blogname && <td className="acms-admin-media-row-blogname">{item.media_blog_name}</td>}
                      {mode === 'edit' && (
                        <td className="acms-admin-table-nowrap">
                          {item.media_editable && (
                            <button type="button" onClick={this.setItem.bind(this, item)} className="acms-admin-btn">
                              {ACMS.i18n('media.edit')}
                            </button>
                          )}
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </form>

          <div className="acms-admin-media-grid" style={{ display: style === 'table' ? 'none' : 'flex' }}>
            {items.map((item, index) => (
              <div
                className="acms-admin-media-grid-item"
                key={item.media_id}
                style={{ width: `${150 * scale}px`, height: `${150 * scale}px` }}
              >
                <div
                  className={classnames('acms-admin-media-grid-figure', { selected: item.checked })}
                  title={item.media_title}
                  role="button"
                  tabIndex={0}
                  onClick={(e) => {
                    if (e.shiftKey && oldItemId !== null) {
                      this.toggleCheckFrom(oldItemId, item);
                    } else {
                      this.toggleCheck(item);
                      this.setState({
                        oldItemId: item.media_id,
                      });
                    }
                  }}
                  onKeyDown={(e) => {
                    // フォーカス中の要素が自分自身だけの場合のみ処理
                    if (e.currentTarget !== document.activeElement) {
                      return; // 子要素にフォーカスがあれば無視
                    }
                    if (e.key === 'Enter' || e.key === ' ') {
                      if (e.shiftKey && oldItemId !== null) {
                        this.toggleCheckFrom(oldItemId, item);
                      } else {
                        this.toggleCheck(item);
                        this.setState({
                          oldItemId: item.media_id,
                        });
                      }
                      // デフォルトのスペースバーの動作を防止
                      e.preventDefault();
                    }
                  }}
                >
                  <div className="acms-admin-media-grid-shadow" />
                  {item.media_type === 'file' && (
                    <div className="acms-admin-media-grid-img-wrap">
                      <img
                        className="acms-admin-media-grid-file-img"
                        src={item.media_thumbnail}
                        style={this.getFileStyle(stateItems[index])}
                        alt=""
                      />
                      <span className="acms-admin-media-grid-filename">{item.media_title}</span>
                    </div>
                  )}
                  {(item.media_type === 'image' || item.media_type === 'svg') && (
                    <img
                      src={`${item.media_thumbnail}?date=${item.media_last_modified}`}
                      style={this.getImageStyle(stateItems[index])}
                      id={`media-image-${index}`}
                      alt=""
                    />
                  )}
                  <div className={classnames('acms-admin-media-grid-flame', { selected: item.checked })}>
                    <div className="acms-admin-media-grid-checkbox" />
                    {mode === 'edit' && item.media_editable && (
                      <div className="acms-admin-media-edit-list-btn-wrap">
                        <button
                          type="button"
                          className="acms-admin-media-edit-btn acms-admin-media-edit-list-btn"
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            this.setItem(item);
                          }}
                        >
                          {ACMS.i18n('media.edit')}
                        </button>
                      </div>
                    )}
                  </div>
                </div>
                <input type="hidden" name="checks[]" value={`${item.media_bid}:${item.media_id}`} />
              </div>
            ))}
          </div>
          <Grid className="acms-admin-margin-top-medium">
            <PaginationRoot
              page={page}
              pageSize={limit}
              total={Math.ceil(total / limit)}
              totalItems={total}
              onChange={(page) =>
                this.fetchMediaList({
                  page,
                })
              }
            >
              <GridItem col={{ md: 6, xs: 12 }}>
                <Pagination />
              </GridItem>
              <GridItem col={{ md: 6, xs: 12 }}>
                <PaginationSummary />
              </GridItem>
            </PaginationRoot>
          </Grid>
        </div>
      </StyledMediaList>
    );
  }
}
