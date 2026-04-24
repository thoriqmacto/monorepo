import type { StoredAuth } from "../auth";

export type LoginPayload = { email: string; password: string };

export type RegisterPayload = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export type AuthAdapter = {
    mode: "bearer" | "cookie";
    login: (payload: LoginPayload) => Promise<StoredAuth>;
    register: (payload: RegisterPayload) => Promise<StoredAuth>;
    me: () => Promise<StoredAuth["user"]>;
    logout: () => Promise<void>;
};
