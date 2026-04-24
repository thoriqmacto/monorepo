import { AUTH_MODE } from "@/lib/env";
import type { AuthAdapter } from "./adapter";
import { bearerAdapter } from "./adapters/bearer";
import { cookieAdapter } from "./adapters/cookie";

export const authAdapter: AuthAdapter = AUTH_MODE === "cookie" ? cookieAdapter : bearerAdapter;

export type { AuthAdapter, LoginPayload, RegisterPayload } from "./adapter";
export {
    clearAuth,
    getToken,
    readAuth,
    writeAuth,
    type StoredAuth,
    type StoredAuthUser,
} from "./storage";
