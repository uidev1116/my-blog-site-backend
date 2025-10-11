/* eslint no-irregular-whitespace: 0 */
import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import keyboardJS from 'keyboardjs';
import classnames from 'classnames';
import copy from 'copy-to-clipboard';
import styled from 'styled-components';
import { JSONTree } from 'react-json-tree';
import axiosLib from '../../../../lib/axios';
import IncrementalSearch from '../../lib/incremental-search';
import Modal from '../../../../components/modal/modal';
import { ExpireLocalStorage } from '../../../../utils';
import HStack from '../../../../components/stack/h-stack';
import SyntaxHighlight from '../../../../components/syntax-highlight/syntax-highlight';
import VisuallyHidden from '../../../../components/visually-hidden';
import { notify } from '../../../../lib/notify';

const jsonTreeTheme = {
  scheme: 'OneDark',
  author: 'Lalit Magant (http://github.com/tilal6991)',
  base00: '#282c34',
  base01: '#353b45',
  base02: '#3e4451',
  base03: '#545862',
  base04: '#565c64',
  base05: '#abb2bf',
  base06: '#b6bdca',
  base07: '#c8ccd4',
  base08: '#e06c75',
  base09: '#d19a66',
  base0A: '#e5c07b',
  base0B: '#98c379',
  base0C: '#56b6c2',
  base0D: '#61afef',
  base0E: '#c678dd',
  base0F: '#be5046',
  tree: {
    margin: 0,
    padding: '10px', // ツリー全体のパディング
    borderRadius: '5px',
  },
};

const StyledVariableTable = styled.div`
  /* stylelint-disable selector-class-pattern */
  height: 100%;
  font-family: Consolas, 'Courier New', Courier, monospace;

  h1 {
    padding: 1em;
  }

  fieldset {
    margin: 1em;
    background-color: lightyellow;
    border: 2px solid gold;
  }

  fieldset.loop {
    background-color: snow;
    border: 3px solid firebrick;
  }

  fieldset.veil {
    background-color: whitesmoke;
    border: 2px dashed royalblue;
  }

  legend {
    padding: 0.3em;
    font-size: x-large;
    font-weight: bold;
    background-color: lightyellow;
    border: 2px solid gold;
  }

  legend.loop {
    background-color: snow;
    border: 3px solid firebrick;
  }

  legend.veil {
    background-color: whitesmoke;
    border: 2px dashed royalblue;
  }

  var {
    display: block;
    padding: 0.5em;
    margin: 0.5em;
    font-size: large;
    font-style: normal;
    font-weight: bold;
    background-color: white;
    border: 3px double black;
  }

  var.deprecated {
    color: gray;

    &::after {
      /* stylelint-disable-next-line no-irregular-whitespace */
      content: '　※廃止予定';
    }
  }

  var span {
    float: right;
    font-size: 14px;
    color: #777;
  }

  legend span {
    margin-left: 20px;
    font-size: 14px;
    color: #777;
  }

  .textLong {
    width: 420px;
  }

  .textTooLong {
    width: 520px;
  }
  /* stylelint-enable selector-class-pattern */
`;

const StyledQuickSearchModal = styled(Modal)`
  /* stylelint-disable selector-class-pattern */
  font-size: 13px;

  .acms-admin-form textarea {
    width: 100%;
    height: 50%;
    margin: 0;
    font-family: Consolas, 'Courier New', Courier, monospace;
    font-size: 1.3em;
    color: #333;
  }

  .acms-admin-admin-title3 {
    padding: 5px 10px;
    margin: 0 0 10px;
    font-size: 14px;
    color: #333;
    background: #fff;
  }

  .acms-admin-table-admin {
    margin: -10px 0 20px;
    border-spacing: 0;
    border-collapse: collapse;
  }

  .acms-admin-label {
    margin-right: 5px;
    font-size: 11px;
    font-weight: bold;
    color: #fff;
    background-color: #1861d8;
  }

  .titleButton {
    display: block;
    width: 100%;
    padding: 0;
    text-align: left;
    background: none;
    border: none;
  }

  .mainTitle {
    display: block;
    font-weight: bold;
    color: #333;
    text-decoration: none;

    &:visited {
      color: #333;
    }
  }

  .subTitle {
    display: block;
    color: #5e6c84;

    span {
      font-size: 12px;
      color: #666;
    }
  }

  .hover .acms-admin-label {
    color: #1861d8 !important;
    background-color: #fff;
  }

  .hover .mainTitle {
    color: #333;
  }

  .hover .subTitle {
    color: #5e6c84;
  }

  .hover .subTitle span {
    color: #666;
  }

  .acms-admin-table-admin td {
    display: table-cell;
    padding: 8px 5px;
    line-height: 1.3;
    background-color: #fff;
    border: none;
    transition: auto;
  }

  .acms-admin-modal-footer {
    padding: 10px 0 0;
    margin: 0 -5px;
    border-top: 1px solid #ccc;
  }

  .acms-admin-list-inline {
    margin: 5px 0;
    font-size: 12px;
    color: #666;
    text-align: right;

    li {
      padding-right: 9px;
      font-size: 12px !important;

      kbd {
        padding: 0 5px;
        font-weight: bold;
        background: #eee;
        border-radius: 2px;
      }
    }
  }

  .initial-mark {
    position: relative;
    width: 30px;
    height: 30px;
    margin-right: 5px;
    margin-left: 5px;
    text-align: center;
    background-color: #aaa;
    border-radius: 50px;

    &::after {
      display: inline-block;
      margin-top: 4px;
      margin-left: 1px;
      font-size: 17px;
      color: #fff;
      content: '';
    }
  }

  .initial-b {
    background-color: #d2b8b8;

    &::after {
      content: 'B';
    }
  }

  .initial-c {
    background-color: #b8d2b8;

    &::after {
      content: 'C';
    }
  }

  .initial-e {
    background-color: #b8ccd2;

    &::after {
      content: 'E';
    }
  }

  .initial-m {
    background-color: #c4b8d2;

    &::after {
      content: 'M';
    }
  }

  .initial-m2 {
    background-color: #9fbf91;

    &::after {
      content: 'M';
    }
  }

  .initial-v {
    background-color: #b8d2c9;

    &::after {
      content: 'V';
    }
  }

  .initial-s {
    background-color: #b6c1da;

    &::after {
      content: 'S';
    }
  }

  .initial-g {
    background-color: #adadad;

    &::after {
      content: 'G';
    }
  }

  .initial-v2 {
    background-color: #b8d2c9;

    &::after {
      content: 'V2';
    }
  }

  .customFieldCopied {
    position: fixed;
    inset: -50px 0 auto;
    z-index: 2501;
    width: 100%;
    padding: 10px;
    font-size: 13px;
    color: #fff;
    background: #5690d8;
    border-radius: 0;
    transition: top 0.2s ease-in;

    &.active {
      top: 0;
    }
  }
  /* stylelint-enable selector-class-pattern */
`;

const CustomJsonLabelRenderer = ({ keyPath }) => {
  const handleClick = (e) => {
    e.preventDefault();
    if (keyPath) {
      const fixPath = keyPath
        .slice()
        .reverse()
        .map((key) => (typeof key === 'number' ? `[${key}]` : key))
        .join('.')
        .replace(/\.([[]\d+])/g, '$1'); // ドットの後にブラケットが続く場合のドットを削除
      copy(fixPath);
    }
  };
  return (
    <span
      onClick={handleClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          handleClick(e);
        }
      }}
      role="button"
      tabIndex={0}
      style={{ cursor: 'pointer' }}
    >
      {keyPath[0]}
    </span>
  );
};

export default QuickSearch = ({ buttons }) => {
  const [lists, setLists] = useState([]);
  const [init, setInit] = useState(false);
  const [menus, setMenus] = useState(null);
  const [snippets, setSnippets] = useState(null);
  const [vars, setVars] = useState(null);
  const [globalVars, setGlobalVars] = useState(null);
  const [number, setNumber] = useState(-1);
  const [isOpen, setIsOpen] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [isSnippetsModalOpen, setIsSnippetsModalOpen] = useState(false);
  const [isVarsModalOpen, setIsVarsModalOpen] = useState(false);
  const [isV2ModuleVarsModalOpen, setIsV2ModuleVarsModalOpen] = useState(false);
  const [modalContent, setModalContent] = useState('');
  const [modalTitlte, setModalTitlte] = useState('');
  const [currentItem, setCurrentItem] = useState(null);
  const copyButtonRef = useRef(null);
  const searchRef = useRef(null);
  const boxRef = useRef(null);
  const isMacOs = navigator.userAgent.match(/Mac|PPC/);
  let v2ModuleVars = null;
  let is = null;

  const moduleEmbeddedJsonDom = document.getElementById('acms-module-data');
  if (moduleEmbeddedJsonDom && moduleEmbeddedJsonDom.dataset.json) {
    v2ModuleVars = JSON.parse(moduleEmbeddedJsonDom.dataset.json);
  }

  const handleAfterOpen = () => {
    is = new IncrementalSearch();
    is.addRequest(
      searchRef.current,
      ACMS.Library.acmsLink({
        bid: ACMS.Config.bid,
      }),
      (data) => {
        setLists(data);
      }
    );
  };

  const handleAfterClose = () => {
    if (is) {
      is.destroy();
    }
    is = null;
  };

  const loadGlobalVars = () => {
    const params = new URLSearchParams();
    params.append('ACMS_POST_Search_GlobalVars', true);
    params.append('formToken', window.csrfToken);
    axiosLib({
      method: 'POST',
      url: window.location.href,
      responseType: 'json',
      data: params,
    }).then((res) => {
      res.data.items.push({
        bid: ACMS.Config.bid,
        title: '\u0025{ROOT_TPL}',
        subtitle: ACMS.i18n('quick_search.root_tpl'),
        url: ACMS.Config.rootTpl,
      });
      setGlobalVars(res.data);
    });
  };

  const setDefinedLists = useCallback(() => {
    const endpoint = `${ACMS.Library.acmsLink({
      tpl: 'acms-code/all.json',
    })}?cache=${new Date().getTime()}`;

    const key = `acms_big_${ACMS.Config.bid}_quick_search_data`;
    const storage = new ExpireLocalStorage();
    const data = storage.load(key);
    if (data) {
      setMenus(data.menus);
      setSnippets(data.snippets);
      setVars(data.vars);
      loadGlobalVars();
    } else {
      axiosLib
        .get(endpoint)
        .then((res) => {
          setMenus(res.data.menus);
          setSnippets(res.data.snippets);
          setVars(res.data.vars);
          storage.save(key, res.data, 1800);
        })
        .then(() => {
          loadGlobalVars();
        });
    }
  }, []);

  const getMode = useMemo(() => {
    if (ACMS.Config.auth !== 'administrator') {
      return 'normal';
    }
    if (keyword.slice(0, 1) === ';') {
      return 'snippets';
    }
    if (keyword.slice(0, 1) === ':') {
      return 'vars';
    }
    if (keyword.slice(0, 1) === '%') {
      return 'global-vars';
    }
    if (keyword.slice(0, 1) === '#') {
      return 'v2module-vars';
    }
    return 'normal';
  }, [keyword]);

  const handleClickEvent = useCallback(
    (item) => {
      if (getMode === 'snippets') {
        showSnippets(item);
      } else if (getMode === 'vars') {
        showVars(item);
      } else if (getMode === 'global-vars') {
        showGlobalVars(item);
      } else if (getMode === 'v2module-vars') {
        showV2ModuleVars(item);
      } else {
        gotoLink(item);
      }
    },
    [getMode]
  );

  const gotoLink = (item) => {
    if (item) {
      location.href = item.url;
    }
  };

  const showSnippets = (item) => {
    if (item) {
      axiosLib.get(item.url).then((res) => {
        const parser = new DOMParser();
        let html = parser.parseFromString(res.data, 'text/html');
        html = (html.querySelector('pre>code') || html.querySelector('textarea')).textContent;
        setModalContent(html);
        setIsSnippetsModalOpen(true);
      });
    }
  };

  const showVars = (item) => {
    if (item) {
      axiosLib.get(item.url).then((res) => {
        const parser = new DOMParser();
        const html = parser.parseFromString(res.data, 'text/html');
        setModalContent(html.body.innerHTML);
        setIsVarsModalOpen(true);
      });
    }
  };

  const showV2ModuleVars = (item) => {
    if (item) {
      setModalTitlte(item.title + (item.subtitle ? `（${item.subtitle}）` : ''));
      setModalContent(item.data);
      setIsV2ModuleVarsModalOpen(true);
    }
  };

  const showGlobalVars = (item) => {
    if (item) {
      copy(item.title);
      notify.info(ACMS.i18n('quick_search.copy_message'));
    }
  };

  const getFilteredSnippets = useMemo(() => {
    const searchWord = keyword ? keyword.toLowerCase().slice(1) : '';

    if (snippets && snippets.items && searchWord) {
      const items = snippets.items.filter((item) => {
        if (
          item.title.toLowerCase().indexOf(searchWord) !== -1 ||
          item.subtitle.toLowerCase().indexOf(searchWord) !== -1
        ) {
          return true;
        }
        return false;
      });
      return {
        title: snippets.title,
        enTitle: snippets.enTitle,
        items,
      };
    }
    return snippets;
  }, [keyword, snippets]);

  const getFilteredVars = useMemo(() => {
    const searchWord = keyword ? keyword.toLowerCase().slice(1) : '';

    if (vars && vars.items && searchWord) {
      const items = vars.items.filter((item) => {
        if (
          item.title.toLowerCase().indexOf(searchWord) !== -1 ||
          item.subtitle.toLowerCase().indexOf(searchWord) !== -1
        ) {
          return true;
        }
        return false;
      });
      return {
        title: vars.title,
        enTitle: vars.enTitle,
        items,
      };
    }
    return vars;
  }, [keyword, vars]);

  const getFilteredGlobalVars = useMemo(() => {
    const searchWord = keyword ? keyword.toLowerCase().slice(1) : '';

    if (globalVars && globalVars.items && searchWord) {
      const items = globalVars.items.filter((item) => {
        if (
          item.title.toLowerCase().indexOf(searchWord) !== -1 ||
          item.subtitle.toLowerCase().indexOf(searchWord) !== -1
        ) {
          return true;
        }
        return false;
      });
      return {
        title: globalVars.title,
        enTitle: globalVars.enTitle,
        items,
      };
    }
    return globalVars;
  }, [keyword, globalVars]);

  const getFilteredV2ModuleVars = useMemo(() => {
    const searchWord = keyword ? keyword.toLowerCase().slice(1) : '';

    if (v2ModuleVars && v2ModuleVars.items && searchWord) {
      const items = v2ModuleVars.items.filter((item) => {
        if (
          item.title.toLowerCase().indexOf(searchWord) !== -1 ||
          item.subtitle.toLowerCase().indexOf(searchWord) !== -1
        ) {
          return true;
        }
        return false;
      });
      return {
        title: v2ModuleVars.title,
        enTitle: v2ModuleVars.enTitle,
        items,
      };
    }
    return v2ModuleVars;
  }, [keyword, v2ModuleVars]);

  const getFilteredMenus = useMemo(() => {
    if (menus && menus.items) {
      const items = menus.items.filter((item) => {
        if (!keyword) {
          return false;
        }
        if (item.title.indexOf(keyword) !== -1 || item.subtitle.indexOf(keyword) !== -1) {
          return true;
        }
        return false;
      });
      return {
        title: menus.title,
        enTitle: menus.enTitle,
        items,
      };
    }
    return menus;
  }, [keyword, menus]);

  const getCombindLists = useCallback(() => {
    if (getMode === 'snippets') {
      return [getFilteredSnippets];
    }
    if (getMode === 'vars') {
      return [getFilteredVars];
    }
    if (getMode === 'global-vars') {
      return [getFilteredGlobalVars];
    }
    if (getMode === 'v2module-vars') {
      return getFilteredV2ModuleVars ? [getFilteredV2ModuleVars] : [];
    }
    if (getFilteredMenus) {
      return [getFilteredMenus, ...lists];
    }
    return lists;
  }, [
    getFilteredSnippets,
    getFilteredVars,
    getFilteredGlobalVars,
    getFilteredV2ModuleVars,
    getFilteredMenus,
    lists,
    getMode,
  ]);

  const getNumber = useCallback(
    (listIndex, index) => {
      const combindLists = getCombindLists();
      let num = 0;
      while (listIndex > 0) {
        listIndex--;
        if (combindLists[listIndex]) {
          if (combindLists[listIndex].items) {
            num += combindLists[listIndex].items.length;
          }
        }
      }
      return num + index;
    },
    [getCombindLists]
  );

  const handleSetKeyword = (keyword) => {
    setKeyword(keyword);
    setNumber(0);
  };

  const closeDialog = () => {
    setIsOpen(false);
    setKeyword('');
    setLists([]);
  };

  const openDialog = () => {
    setIsOpen(true);
    setKeyword('');
    setLists([]);
  };

  const toggleDialog = useCallback(() => {
    if (init === false) {
      setInit(true);
      setDefinedLists();
    }
    if (isOpen) {
      closeDialog();
    } else {
      openDialog();
    }
  }, [init, isOpen, setDefinedLists]);

  const handleClose = () => {
    closeDialog();
  };

  const gotoNextItem = useCallback(() => {
    const combindLists = getCombindLists();
    const maxNumber = getNumber(combindLists.length, 0) - 1;
    const nextNumber = number + 1 > maxNumber ? 0 : number + 1;
    setNumber(nextNumber);
  }, [number, getCombindLists, getNumber]);

  const gotoPrevItem = useCallback(() => {
    const combindLists = getCombindLists();
    const maxNumber = getNumber(combindLists.length, 0) - 1;
    if (!isOpen) {
      return;
    }
    const nextNumber = number - 1 < 0 ? maxNumber : number - 1;
    setNumber(nextNumber);
  }, [number, getCombindLists, getNumber, isOpen]);

  const getCurrentItem = useCallback(() => {
    const combindLists = getCombindLists();
    let itemNum = 0;
    let res = false;
    combindLists.forEach((list) => {
      list.items.forEach((item) => {
        if (number === itemNum) {
          res = item;
        }
        itemNum++;
      });
    });
    return res;
  }, [number, getCombindLists]);

  const handleSetNumber = (listIndex, index) => {
    const number = getNumber(listIndex, index);
    setNumber(number);
  };

  const handleCloseSnippetsModal = () => {
    setIsSnippetsModalOpen(false);
    // setKeyword(keyword.replace(/^(:|;|#)(.*)/g, '$1'));
    requestAnimationFrame(() => {
      if (searchRef.current) {
        searchRef.current.focus();
      }
    });
  };

  const handleCloseVarsModal = () => {
    setIsVarsModalOpen(false);
    // setKeyword(keyword.replace(/^(:|;|#)(.*)/g, '$1'));
    requestAnimationFrame(() => {
      if (searchRef.current) {
        searchRef.current.focus();
      }
    });
  };

  const handleCloseV2ModuleVarsModal = () => {
    setIsV2ModuleVarsModalOpen(false);
    // setKeyword(keyword.replace(/^(:|;|#)(.*)/g, '$1'));
    requestAnimationFrame(() => {
      if (searchRef.current) {
        searchRef.current.focus();
      }
    });
  };

  const handleCopySnippet = () => {
    copy(modalContent);
    notify.info(ACMS.i18n('quick_search.copy_message'));
  };

  const handleCopyV2ModuleVars = () => {
    copy(JSON.stringify(modalContent, null, 2));
    notify.info(ACMS.i18n('quick_search.copy_message'));
  };

  const getInitialClassByName = (name) => {
    switch (name) {
      case 'Blogs':
        return 'initial-b';
      case 'Categories':
        return 'initial-c';
      case 'Entries':
        return 'initial-e';
      case 'Modules':
        return 'initial-m';
      case 'Menu':
        return 'initial-m2';
      case 'Vars':
        return 'initial-v';
      case 'Snippets':
        return 'initial-s';
      case 'Global vars':
        return 'initial-g';
      case 'V2 Module vars':
        return 'initial-v2';
      default:
        return '';
    }
  };

  useEffect(() => {
    if (buttons && buttons.length > 0) {
      buttons.forEach((button) => {
        button.addEventListener('click', toggleDialog);
      });
    }
    keyboardJS.bind(ACMS.Config.quickSearchCommand, (e) => {
      e.preventDefault();
      toggleDialog();
    });
    keyboardJS.bind(['tab', 'down'], (e) => {
      if (isOpen && !isSnippetsModalOpen && !isVarsModalOpen && !isV2ModuleVarsModalOpen) {
        e.preventDefault();
        gotoNextItem();
      }
    });
    keyboardJS.bind(['shift + tab', 'up'], (e) => {
      if (isOpen && !isSnippetsModalOpen && !isVarsModalOpen && !isV2ModuleVarsModalOpen) {
        e.preventDefault();
        gotoPrevItem();
      }
    });
    keyboardJS.bind(['enter'], (e) => {
      if (isOpen && !isSnippetsModalOpen && !isVarsModalOpen && !isV2ModuleVarsModalOpen) {
        e.preventDefault();
        const item = getCurrentItem();
        handleClickEvent(item);
      }
    });
    return () => {
      if (buttons && buttons.length > 0) {
        buttons.forEach((button) => {
          button.removeEventListener('click', toggleDialog);
        });
      }
      keyboardJS.reset();
    };
  }, [
    isOpen,
    isSnippetsModalOpen,
    isVarsModalOpen,
    isV2ModuleVarsModalOpen,
    buttons,
    getCurrentItem,
    toggleDialog,
    handleClickEvent,
    gotoNextItem,
    gotoPrevItem,
  ]);

  useEffect(() => {
    if (currentItem && boxRef.current) {
      const boxTop = boxRef.current.getBoundingClientRect().top;
      const boxBottom = boxTop + boxRef.current.offsetHeight;
      const itemTop = currentItem.getBoundingClientRect().top;
      const itemBottom = itemTop + currentItem.offsetHeight;
      const positionTop = itemTop - boxTop;
      const positionBottom = boxBottom - itemBottom;
      if (positionTop < 0 || positionBottom < 0) {
        boxRef.current.scrollTop += positionTop;
      }
    }
  }, [currentItem]);

  const renderJsonLabel = (keyPath, nodeType, expanded, expandable) => {
    return <CustomJsonLabelRenderer keyPath={keyPath} />;
  };

  return (
    <>
      <StyledQuickSearchModal
        isOpen={isOpen}
        onClose={handleClose}
        id="quick-search-dialog"
        className="acms-admin-modal-middle"
        dialogClassName="acms-admin-modal-quick-search"
        onAfterOpen={handleAfterOpen}
        onAfterClose={handleAfterClose}
        aria-labelledby="acms-qucik-search-dialog-title"
      >
        <StyledQuickSearchModal.Body>
          <div className="acms-admin-form" style={{ paddingTop: '15px', paddingBottom: '15px' }}>
            <label className="acms-admin-width-max">
              <VisuallyHidden id="acms-qucik-search-dialog-title">{ACMS.i18n('quick_search.title')}</VisuallyHidden>
              <input
                type="text"
                ref={searchRef}
                style={{ fontSize: '24px', fontWeight: 'bold' }}
                placeholder={ACMS.i18n('quick_search.input_placeholder')}
                className="acms-admin-form-width-full acms-admin-form-large acms-admin-margin-bottom-small"
                onInput={(e) => {
                  handleSetKeyword(e.target.value);
                }}
              />
            </label>
          </div>
          <div ref={boxRef} className="acms-admin-modal-middle-scroll">
            {getCombindLists().map((list, listIndex) => (
              <div key={`label-${list.enTitle}`}>
                {list && list.items.length > 0 && (
                  <div>
                    <h2 className="acms-admin-admin-title3" style={{ background: '#FFF' }}>
                      {list.title}
                    </h2>
                    <table className="acms-admin-table-admin acms-admin-form acms-admin-table-hover">
                      <tbody key={`body-${list.enTitle}`}>
                        {list.items.map((item, index) => (
                          <tr
                            key={getNumber(listIndex, index)}
                            ref={(element) => {
                              if (getNumber(listIndex, index) === number) setCurrentItem(element);
                            }}
                            onMouseMove={() => handleSetNumber(listIndex, index)}
                            className={classnames({ hover: getNumber(listIndex, index) === number })}
                          >
                            <td style={{ width: '1px', wordBreak: 'break-all' }}>
                              <div className={classnames('initial-mark', getInitialClassByName(list.enTitle))} />
                            </td>
                            <td>
                              {getMode === 'normal' ? (
                                <a href={item.url}>
                                  <span className="mainTitle">{item.title}</span>
                                  <span className="subTitle">
                                    {item.subtitle} <span>{item.blogName}</span>
                                  </span>
                                </a>
                              ) : (
                                <button className="titleButton" type="button" onClick={() => handleClickEvent(item)}>
                                  <span className="mainTitle">{item.title}</span>
                                  <span className="subTitle">
                                    {item.subtitle} <span>{item.blogName}</span>
                                  </span>
                                </button>
                              )}
                            </td>
                            {getMode !== 'normal' ? (
                              <td style={{ textAlign: 'right', wordBreak: 'break-all' }}>
                                {getMode === 'global-vars' && <span style={{ paddingRight: '10px' }}>{item.url}</span>}
                              </td>
                            ) : (
                              <td style={{ width: '1px', textAlign: 'right', whiteSpace: 'nowrap' }}>
                                {item.bid && (
                                  <span className="acms-admin-label">
                                    bid:
                                    {item.bid}
                                  </span>
                                )}
                                {item.cid && (
                                  <span className="acms-admin-label">
                                    cid:
                                    {item.cid}
                                  </span>
                                )}
                                {item.eid && (
                                  <span className="acms-admin-label">
                                    eid:
                                    {item.eid}
                                  </span>
                                )}
                                {item.mid && (
                                  <span className="acms-admin-label">
                                    mid:
                                    {item.mid}
                                  </span>
                                )}
                              </td>
                            )}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            ))}
          </div>
        </StyledQuickSearchModal.Body>
        <StyledQuickSearchModal.Footer>
          <ul className="acms-admin-list-inline">
            <li>
              <kbd>tab</kbd> or
              <kbd>⇅</kbd> {ACMS.i18n('quick_search.choice')}
            </li>
            <li>
              <kbd>↵</kbd> {ACMS.i18n('quick_search.move')}
            </li>
            <li>
              <kbd>esc</kbd> {ACMS.i18n('quick_search.close')}
            </li>
            {ACMS.Config.auth === 'administrator' && (
              <>
                <li>
                  <kbd>{isMacOs ? <span>⌘K</span> : <span>ctl+k</span>}</kbd> {ACMS.i18n('quick_search.open')}
                </li>
                <li>
                  <kbd>;</kbd> {ACMS.i18n('quick_search.snippets')}
                </li>
                <li>
                  <kbd>#</kbd> {ACMS.i18n('quick_search.v2module_vars')}
                </li>
                <li>
                  <kbd>:</kbd> {ACMS.i18n('quick_search.vars')}
                </li>
                <li>
                  <kbd>%</kbd> {ACMS.i18n('quick_search.g_vars')}
                </li>
              </>
            )}
          </ul>
        </StyledQuickSearchModal.Footer>
      </StyledQuickSearchModal>
      <Modal
        isOpen={isSnippetsModalOpen}
        onClose={handleCloseSnippetsModal}
        focusTrapOptions={{ initialFocus: () => copyButtonRef.current }}
      >
        <Modal.Header>{ACMS.i18n('quick_search.snippets')}</Modal.Header>
        <Modal.Body>
          <div className="acms-admin-form">
            <SyntaxHighlight language="twig" style={{ height: '400px', overflow: 'scroll', borderRadius: '5px' }}>
              {modalContent}
            </SyntaxHighlight>
          </div>
        </Modal.Body>
        <Modal.Footer>
          <HStack display="inline-flex">
            <button type="button" onClick={handleCloseSnippetsModal} className="acms-admin-btn">
              {ACMS.i18n('quick_search.close')}
            </button>
            <button ref={copyButtonRef} type="button" onClick={handleCopySnippet} className="acms-admin-btn">
              {ACMS.i18n('quick_search.copy')}
            </button>
          </HStack>
        </Modal.Footer>
      </Modal>
      <Modal isOpen={isVarsModalOpen} onClose={handleCloseVarsModal}>
        <Modal.Header>{ACMS.i18n('quick_search.vars')}</Modal.Header>
        <Modal.Body>
          <div className="acms-admin-form">
            <div style={{ paddingTop: '10px', paddingBottom: '10px' }}>
              {/* eslint-disable-next-line react/no-danger */}
              <StyledVariableTable dangerouslySetInnerHTML={{ __html: modalContent }} />
            </div>
          </div>
        </Modal.Body>
        <Modal.Footer>
          <div>
            <button type="button" onClick={handleCloseVarsModal} className="acms-admin-btn">
              {ACMS.i18n('quick_search.close')}
            </button>
          </div>
        </Modal.Footer>
      </Modal>
      <Modal isOpen={isV2ModuleVarsModalOpen} onClose={handleCloseV2ModuleVarsModal}>
        <Modal.Header>{modalTitlte}</Modal.Header>
        <Modal.Body>
          <div className="acms-admin-form">
            <div style={{ paddingTop: '10px', paddingBottom: '10px' }}>
              <JSONTree
                data={modalContent}
                theme={jsonTreeTheme}
                hideRoot
                invertTheme={false}
                shouldExpandNodeInitially={(keyPath, data, level) => {
                  return level < 1;
                }}
                labelRenderer={renderJsonLabel}
                style={{ padding: '10px' }}
              />
            </div>
          </div>
        </Modal.Body>
        <Modal.Footer>
          <HStack display="inline-flex">
            <button type="button" onClick={handleCloseV2ModuleVarsModal} className="acms-admin-btn">
              {ACMS.i18n('quick_search.close')}
            </button>
            <button ref={copyButtonRef} type="button" onClick={handleCopyV2ModuleVars} className="acms-admin-btn">
              {ACMS.i18n('quick_search.copy')}
            </button>
          </HStack>
        </Modal.Footer>
      </Modal>
    </>
  );
};
