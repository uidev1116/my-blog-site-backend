import { logRepository } from './repository';

interface DeprecatedOptions {
  since?: string;
  version?: string;
  alternative?: string;
  link?: string;
  hint?: string;
}

/**
 * 本番環境でない場合に非推奨ログを表示するためのユーティリティ
 *
 * @param message 非推奨ログメッセージ
 * @param options 非推奨ログオプション
 * @param options.since 非推奨ログオプション
 * @param options.version 非推奨ログオプション
 * @param options.alternative 非推奨ログオプション
 * @param options.link 非推奨ログオプション
 * @param options.hint 非推奨ログオプション
 *
 * @example
 * ```js
 * import deprecated from 'path/to/deprecated';
 *
 * deprecated( 'Built-in JS「incremental search」', {
 * 	since: '2025.01.01',
 * 	version: '3.2.0',
 * 	alternative: 'HTML form attribute',
 * 	link: 'https://example.com',
 * 	hint: 'You may find it beneficial to transition gradually.',
 * } );
 * ```
 */
export default function deprecated(feature: string, options: DeprecatedOptions = {}): void {
  if (!ACMS.Library.isDebugMode()) {
    return;
  }

  const { since, version, alternative, link, hint } = options;

  const sinceMessage = since ? ACMS.i18n('deprecated.option.since', { since }) : '';
  const removeMessage = version
    ? ACMS.i18n('deprecated.option.will_be_removed', { version })
    : ACMS.i18n('deprecated.option.may_be_removed');
  const alternativeMessage = alternative ? `\n${ACMS.i18n('deprecated.option.alternative', { alternative })}` : '';
  const linkMessage = link ? `\n${ACMS.i18n('deprecated.option.link', { link })}` : '';
  const hintMessage = hint ? `\n${ACMS.i18n('deprecated.option.hint', { hint })}` : '';
  const message = ACMS.i18n('deprecated.message', {
    feature,
    since: sinceMessage,
    remove: removeMessage,
    alternative: alternativeMessage,
    link: linkMessage,
    hint: hintMessage,
  });

  // Skip if already logged.
  if (logRepository.has(message)) {
    return;
  }

  // eslint-disable-next-line no-console
  console.warn(message);

  logRepository.add(message);
}
