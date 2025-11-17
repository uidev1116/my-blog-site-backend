import { useCallback, useState } from 'react';
import ContentLoader from 'react-content-loader';
import type { AxiosRequestConfig, AxiosResponse } from 'axios';
import axios from 'axios';
import { nl2br } from '../../../../utils/react';
import axiosLib from '../../../../lib/axios';
import SyntaxHighlight from '../../../../components/syntax-highlight/syntax-highlight';
import Modal from '../../../../components/modal/modal';
import { parseQuery } from '../../../../utils';
import Alert from '../../../../components/alert/alert';
import { Tooltip } from '../../../../components/tooltip';
import VStack from '../../../../components/stack/v-stack';
import useEffectOnce from '../../../../hooks/use-effect-once';
import useClipboard from '../../../../hooks/use-clipboard';

interface Log {
  id: number;
  success: boolean;
  datetime: string;
  level: string;
  levelName: string;
  suid: number;
  sessionUserName: string;
  sessionUserMail: string;
  bid: number;
  blogName: string;
  message: string;
  url: string;
  isManagedDomain: boolean;
  ua: string;
  referer: string;
  ipAddress: string;
  method: string;
  httpStatus: string;
  responseTime: number;
  eid: null | number;
  cid: null | number;
  uid: null | number;
  rid: null | number;
  ruleName: string;
  extra: string;
  context: string;
  switchUserName: string;
  switchUserMail: string;
  reqHeader: string;
  reqBody: string;
  acmsPost: string;
}

const TablePlaceholder = () => (
  <ContentLoader viewBox="0 0 400 190" speed={0.9}>
    <rect x="0" y="0" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="0" rx="1" ry="1" width="345" height="8" />
    <rect x="0" y="16" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="16" rx="1" ry="1" width="270" height="8" />
    <rect x="0" y="32" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="32" rx="1" ry="1" width="230" height="8" />
    <rect x="0" y="48" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="48" rx="1" ry="1" width="345" height="8" />
    <rect x="0" y="64" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="64" rx="1" ry="1" width="140" height="8" />
    <rect x="0" y="80" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="80" rx="1" ry="1" width="200" height="8" />
    <rect x="0" y="96" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="96" rx="1" ry="1" width="345" height="8" />
    <rect x="0" y="112" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="112" rx="1" ry="1" width="270" height="8" />
    <rect x="0" y="128" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="128" rx="1" ry="1" width="230" height="8" />
    <rect x="0" y="144" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="144" rx="1" ry="1" width="345" height="8" />
    <rect x="0" y="160" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="160" rx="1" ry="1" width="140" height="8" />
    <rect x="0" y="176" rx="1" ry="1" width="50" height="8" />
    <rect x="55" y="176" rx="1" ry="1" width="200" height="8" />
  </ContentLoader>
);

interface AuditLogDetailModalProps {
  buttons: NodeListOf<HTMLButtonElement>;
}

const AuditLogDetailModal = ({ buttons }: AuditLogDetailModalProps) => {
  const [isOpen, setIsOpen] = useState<boolean>(false);
  const [isReady, setIsReady] = useState<boolean>(false);
  const [logInfo, setLogInfo] = useState<Log>({} as Log);
  const [errorMessage, setErrorMessage] = useState<string>('');
  const { copy, copied, reset } = useClipboard();

  const fetchLogInfo = async (id: string) => {
    setLogInfo({} as Log);
    setIsReady(false);
    setIsOpen(true);

    const params = new URLSearchParams();
    params.append('ACMS_POST_Logger_Info', 'post');
    params.append('id', id);
    params.append('formToken', window.csrfToken);

    const axiosOptions: AxiosRequestConfig = {
      method: 'POST',
      url: window.location.href,
      responseType: 'json',
      data: params,
    };

    try {
      const response: AxiosResponse<Log> = await axiosLib(axiosOptions);
      const json = response.data;
      if (response.status === 200) {
        if (json && json.success === true) {
          setLogInfo(json);
          setIsReady(true);

          if ('history' in window) {
            history.replaceState(null, '', `#id=${json.id}`);
          }
        }
      } else {
        setErrorMessage('ログの取得に失敗しました');
      }
    } catch (e) {
      if (axios.isAxiosError(e) && e.response && e.response.status === 400) {
        setErrorMessage(`ログの取得に失敗しました。${e.message}`);
      }
    }
  };

  useEffectOnce(() => {
    const handleClick = (e: MouseEvent) => {
      e.preventDefault();
      if (!(e.currentTarget instanceof HTMLButtonElement)) {
        return;
      }
      const id = e.currentTarget.getAttribute('data-id');
      if (id) {
        fetchLogInfo(id);
      }
    };

    [].forEach.call(buttons, (button: HTMLButtonElement) => {
      button.addEventListener('click', handleClick);
    });

    if (window.location.hash) {
      const { id } = parseQuery(window.location.hash.slice(1));
      if (id) {
        fetchLogInfo(id);
      }
    }

    return () => {
      [].forEach.call(buttons, (button: HTMLButtonElement) => {
        button.removeEventListener('click', handleClick);
      });
    };
  });

  const handleCopy = useCallback(() => {
    copy(JSON.stringify(logInfo, null, 2));
    setTimeout(() => {
      reset();
    }, 2000);
  }, [copy, logInfo, reset]);

  const ModalContent = (
    <VStack align="stretch" spacing="1rem">
      <div
        style={{
          display: 'flex',
          justifyContent: 'flex-end',
        }}
      >
        <button
          type="button"
          className="acms-admin-btn-admin"
          onClick={handleCopy}
          data-tooltip-id="log-detail-copied-tooltip"
          data-tooltip-content={ACMS.i18n('logger.modal.clipboardMessage')}
          data-data-tooltip-place="left"
          data-tooltip-variant="dark"
        >
          {ACMS.i18n('logger.modal.clipboardButton')}
        </button>
        <Tooltip id="log-detail-copied-tooltip" isOpen={copied} openEvents={{ mouseover: false, focus: false }} />
      </div>
      {errorMessage && (
        <div>
          <Alert type="danger">{errorMessage}</Alert>
        </div>
      )}
      <div className="acms-admin-log-table" style={{ overflowX: 'scroll' }}>
        {!isReady && <TablePlaceholder />}
        {isReady && (
          <table className="acms-admin-table acms-admin-table-striped">
            <tbody>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.datetime')}</th>
                <td>{logInfo.datetime}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.level')}</th>
                <td>{logInfo.levelName}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.message')}</th>
                <td>{nl2br(logInfo.message)}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.blog')}</th>
                <td>
                  {logInfo.blogName && (
                    <>
                      {logInfo.blogName}
                      &nbsp; &#40;id=
                      {logInfo.bid}
                      &#41;
                    </>
                  )}
                </td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.loginUserName')}</th>
                <td>
                  {logInfo.sessionUserName && (
                    <>
                      {logInfo.sessionUserName}
                      &nbsp; &#40;
                      {logInfo.sessionUserMail}
                      &#41;
                    </>
                  )}
                </td>
              </tr>
              {logInfo.switchUserName && (
                <tr>
                  <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.switchUserName')}</th>
                  <td>
                    {logInfo.switchUserName}
                    &nbsp; &#40;
                    {logInfo.switchUserMail}
                    &#41;
                  </td>
                </tr>
              )}
              <tr>
                <th className="acms-admin-table-nowrap">URL</th>
                <td>
                  {logInfo.isManagedDomain ? (
                    <a href={logInfo.url} target="_blank" rel="noreferrer">
                      {logInfo.url}
                    </a>
                  ) : (
                    logInfo.url
                  )}
                </td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">UA</th>
                <td>{logInfo.ua}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.ipAddress')}</th>
                <td>{logInfo.ipAddress}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.referer')}</th>
                <td>{logInfo.referer}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.method')}</th>
                <td>{logInfo.method}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.httpStatus')}</th>
                <td>{logInfo.httpStatus}</td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.responseTime')}</th>
                <td>
                  {logInfo.responseTime}
                  &nbsp; ms
                </td>
              </tr>
              <tr>
                <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.ruleName')}</th>
                <td>
                  {logInfo.rid && (
                    <>
                      {logInfo.ruleName}
                      &nbsp; &#40;id=
                      {logInfo.rid}
                      &#41;
                    </>
                  )}
                </td>
              </tr>
              {logInfo.acmsPost && (
                <tr>
                  <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.postModule')}</th>
                  <td>{logInfo.acmsPost}</td>
                </tr>
              )}
              {logInfo.reqHeader && (
                <tr>
                  <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.httpRequestHeader')}</th>
                  <td>
                    <SyntaxHighlight language="json">{JSON.stringify(logInfo.reqHeader, null, 2)}</SyntaxHighlight>
                  </td>
                </tr>
              )}
              {logInfo.reqBody && (
                <tr>
                  <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.httpRequestBody')}</th>
                  <td>
                    <SyntaxHighlight language="json">{JSON.stringify(logInfo.reqBody, null, 2)}</SyntaxHighlight>
                  </td>
                </tr>
              )}
              {logInfo.context && (
                <tr>
                  <th className="acms-admin-table-nowrap">{ACMS.i18n('logger.modal.extraInfo')}</th>
                  <td>
                    <SyntaxHighlight language="json">{JSON.stringify(logInfo.context, null, 2)}</SyntaxHighlight>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </VStack>
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => {
        setIsOpen(false);
        if ('history' in window) {
          window.history.replaceState(null, '', location.pathname + location.search);
        }
      }}
      className="acms-admin-logger-modal"
      size="large"
      aria-labelledby="acms-admin-logger-modal-title"
    >
      <Modal.Header>{ACMS.i18n('logger.modal.title')}</Modal.Header>
      <Modal.Body>{ModalContent}</Modal.Body>
    </Modal>
  );
};

export default AuditLogDetailModal;
