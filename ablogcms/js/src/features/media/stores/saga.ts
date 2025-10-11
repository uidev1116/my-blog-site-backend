import { call, put, takeEvery, select } from 'redux-saga/effects';
import axiosLib from '../../../lib/axios';

import * as types from '../constants';
import {
  setMediaList,
  setMediaLargeSize,
  setMediaLastPage,
  setMediaConfig,
  setMediaArchives,
  setMediaTags,
  setMediaTotal,
  setLoading,
  setMediaExtensions,
} from './actions';
import { MediaItem, MediaStateProps } from '../types';

function fetchJSON(url: string) {
  return new Promise((resolve) => {
    axiosLib.get(url).then((res) => {
      resolve(res.data);
    });
  });
}

function* fetchTagList() {
  const url = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      tpl: 'ajax/edit/media-tag.json',
    },
    false
  );
  const data: string[] = yield call(fetchJSON, url);
  yield put(setMediaTags(data));
}

function* fetchMediaList({ config = {} }: ReturnType<typeof setMediaConfig>) {
  const state: MediaStateProps = yield select();
  const setting = { ...state.config, ...config };
  const url = ACMS.Library.acmsLink(
    {
      tpl: 'ajax/edit/media.json',
      bid: ACMS.Config.bid,
      page: setting.page,
      tag: setting.tag,
      keyword: setting.keyword,
      order: setting.order,
      limit: setting.limit,
      date: setting.date,
      Query: {
        type: setting.filetype,
        ext: setting.fileext,
        year: setting.year,
        month: setting.month,
        owner: setting.owner ? 'true' : 'false',
        blogAxis: setting.blogAxis ? 'true' : 'false',
        cache: new Date().getTime().toString(),
      },
    },
    false
  );
  yield put(setLoading(true));
  yield put(setLoading(false));
  const data: {
    items: MediaItem[];
    pageAmount: number;
    total: number;
    largeSize: number;
    archives: string[];
    tags: string[];
    extensions: string[];
  } = yield call(fetchJSON, url);
  const { items } = data;
  const newItems = items.map((item) => {
    const find = state.items.find((stateItem) => {
      if (item.media_id === stateItem.media_id) {
        return true;
      }
      return false;
    });
    if (find) {
      return { ...item, checked: find.checked };
    }
    return item;
  });
  yield put(setMediaList(newItems));
  yield put(setMediaLastPage(data.pageAmount));
  yield put(setMediaTotal(data.total));
  yield put(setMediaLargeSize(data.largeSize));
  yield put(setMediaArchives(data.archives));
  yield put(setMediaTags(data.tags));
  yield put(setMediaExtensions(data.extensions));
  if (config) {
    yield put(setMediaConfig(config));
  }
}

export default function* rootSaga() {
  yield takeEvery(types.FETCHMEDIALIST, fetchMediaList);
  yield takeEvery(types.FETCHTAGLIST, fetchTagList);
}
