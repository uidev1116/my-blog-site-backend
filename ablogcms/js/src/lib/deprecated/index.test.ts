/* eslint-disable no-console */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import deprecated from './index';
import { logRepository } from './repository';

type I18nMessages = {
  'deprecated.option.since': string;
  'deprecated.option.will_be_removed': string;
  'deprecated.option.may_be_removed': string;
  'deprecated.option.alternative': string;
  'deprecated.option.link': string;
  'deprecated.option.hint': string;
  'deprecated.message': string;
};

// Mock ACMS.Library.isDebugMode and ACMS.i18n
vi.stubGlobal('ACMS', {
  Library: {
    isDebugMode: vi.fn(),
  },
  i18n: vi.fn((key: keyof I18nMessages, params?: Record<string, string>) => {
    const messages: I18nMessages = {
      'deprecated.option.since': 'Since: {since}',
      'deprecated.option.will_be_removed': 'Will be removed in version {version}',
      'deprecated.option.may_be_removed': 'May be removed in a future version',
      'deprecated.option.alternative': 'Use {alternative} instead',
      'deprecated.option.link': 'See: {link}',
      'deprecated.option.hint': 'Hint: {hint}',
      'deprecated.message': '{feature}\n{since}\n{remove}{alternative}{link}{hint}',
    };
    let message = messages[key];
    Object.entries(params || {}).forEach(([k, v]) => {
      message = message.replace(`{${k}}`, v);
    });
    return message;
  }),
});

describe('deprecated', () => {
  beforeEach(() => {
    // Reset console.warn mock before each test
    vi.spyOn(console, 'warn').mockClear();
    // Reset logRepository before each test
    logRepository.clear();
    // Reset ACMS.i18n mock
    vi.mocked(ACMS.i18n).mockClear();
  });

  it('should not show warning when debug mode is disabled', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(false);
    const message = 'Test warning message';

    // Act
    deprecated(message);

    // Assert
    expect(console.warn).not.toHaveBeenCalled();
  });

  it('should show warning when debug mode is enabled', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const message = 'Test warning message';

    // Act
    deprecated(message);

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining(message));
  });

  it('should show warning only once for the same message', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const message = 'Test warning message';

    // Act
    deprecated(message);
    deprecated(message);
    deprecated(message);

    // Assert
    expect(console.warn).toHaveBeenCalledTimes(1);
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining(message));
  });

  it('should include since information when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const since = '2.0.0';

    // Act
    deprecated(feature, { since });

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Since: 2.0.0'));
  });

  it('should include version information when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const version = '3.0.0';

    // Act
    deprecated(feature, { version });

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Will be removed in version 3.0.0'));
  });

  it('should include alternative information when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const alternative = 'newFeature()';

    // Act
    deprecated(feature, { alternative });

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Use newFeature() instead'));
  });

  it('should include link information when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const link = 'https://example.com/docs';

    // Act
    deprecated(feature, { link });

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('See: https://example.com/docs'));
  });

  it('should include hint information when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const hint = 'Consider migrating to the new API';

    // Act
    deprecated(feature, { hint });

    // Assert
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Hint: Consider migrating to the new API'));
  });

  it('should combine all options when provided', () => {
    // Arrange
    vi.mocked(ACMS.Library.isDebugMode).mockReturnValue(true);
    const feature = 'Test feature';
    const options = {
      since: '2.0.0',
      version: '3.0.0',
      alternative: 'newFeature()',
      link: 'https://example.com/docs',
      hint: 'Consider migrating to the new API',
    };

    // Act
    deprecated(feature, options);

    // Assert
    const warning = (console.warn as unknown as { mock: { calls: string[][] } }).mock.calls[0][0];
    expect(warning).toContain('Test feature');
    expect(warning).toContain('Since: 2.0.0');
    expect(warning).toContain('Will be removed in version 3.0.0');
    expect(warning).toContain('Use newFeature() instead');
    expect(warning).toContain('See: https://example.com/docs');
    expect(warning).toContain('Hint: Consider migrating to the new API');
  });
});
