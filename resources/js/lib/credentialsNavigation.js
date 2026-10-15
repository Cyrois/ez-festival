export const credentialsHome = (permissions = {}) =>
    permissions['credentials.passes.edit']
        ? '/credentials/passes'
        : '/credentials/entitlements';
