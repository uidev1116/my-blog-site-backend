/* eslint @typescript-eslint/no-explicit-any: 0 */

/// <reference types="i18next" />

interface Dispatch {
  (context: HTMLElement | Document | JQuery): void;
  validator: (
    form: HTMLFormElement,
    options?: Partial<import('../lib/validator/types').ValidatorOptions>
  ) => Promise<import('../lib/validator').default>;
  [key: string]: any;
}

interface acms {
  Dispatch: Dispatch;
  Dispatch2: (context: HTMLElement | Document) => void;
  Library: {
    acmsPath: typeof import('../lib/acmsPath/acmsPath').default;
    parseAcmsPath: typeof import('../lib/acmsPath/parseAcmsPath').default;
    acmsLink: typeof import('../built-in/lib/acmsLink').default;
    createAcmsContextFromFormData: typeof import('../built-in/lib/createAcmsContextFromFormData').default;
    notify: typeof import('../lib/notify').notify;
    pending: typeof import('../lib/pending').pending;
    dialog: typeof import('../lib/dialog').dialog;
    fileiconPath: (extension: string) => string;
    validator: typeof import('../built-in/lib/validator').default;
    isDebugMode: () => boolean;
    deprecated: typeof import('../lib/deprecated').default;
    ResizeImage: (elm: HTMLElement) => import('../lib/resize-image/resize-image').default;
    modal: InstanceType<typeof import('../lib/modal/store/modal-manager').default>;
    geolocation: (
      successCallable: (latitude: number, longitude: number) => void,
      errorCallable: (message: string) => void
    ) => void;
    tab: typeof import('../built-in/lib/tab').default;
  } & Record<string, any>;
  Config: {
    offset: string;
    jsDir: string;
    themesDir: string;
    ARCHIVES_DIR: string;
    MEDIA_ARCHIVES_DIR: string;
    HTTP_MEDIA_ARCHIVES_DIR: string;
    MEDIA_STORAGE_DIR: string;
    bid: string;
    aid?: string;
    cid?: string;
    uid?: string;
    eid?: string;
    rvid?: string;
    rid: string;
    mid: string;
    setId: string;
    bcd: string;
    ccd: string;
    ecd: string;
    suid?: string;
    admin: string;
    keyword: string;
    auth: 'subscriber' | 'contributor' | 'editor' | 'administrator' | '';
    dbCharset?: string;
    domains?: string;
    edition: 'standard' | 'professional' | 'enterprise';
    fulltimeSSL: '1' | '0';
    jQuery: string;
    jQueryMigrate: string;
    jpegQuality: string;
    jsRoot: string;
    lgImg: string;
    mediaClientResize: 'on' | 'off';
    mediaLibrary: 'on' | 'off';
    mfu: string;
    pms: string;
    root: string;
    rootTpl?: string;
    scriptRoot: string;
    searchEngineKeyword: string;
    timemachinePreviewDefaultDevice: string;
    timemachinePreviewHasHistoryDevice: string;
    fileiconDir: string;
    uaGroup: string;
    umfs: string;
    urlPreviewExpire: string;
    v: string;
    timeMachineMode?: 'true';
    multiDomain: '1' | '0';
    cache?: string;
    segments: import('../lib/acmsPath/types').AcmsPathSegments;
    hash: string;
    limitOptions: string[];
    defaultLimit: string;
    entryEditPageType: 'admin' | 'front' | 'normal';
    isDebugMode: '1' | '0';
    Gmap: {
      sensor: 'true' | 'false';
    };
  } & Record<string, any>;
  Load: any;
  dispatchEvent(eventName: string, dom?: HTMLElement | Document, detail?: unknown, options?: CustomEventInit): void;
  addListener(eventName: string, fn: (event: any) => void): void;
  Loaded: (fn: () => void) => void;
  i18n: import('i18next').TFunction & {
    lng: string;
  };
  unitEditor: import('../features/unit-editor/core').Editor | null;
}

interface Window {
  ACMS: acms;
}

declare let ACMS: acms;
declare let window: Window;
