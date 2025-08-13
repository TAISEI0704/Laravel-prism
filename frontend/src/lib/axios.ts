import axios from "axios";

// APIクライアントの基本設定
axios.defaults.baseURL = "http://localhost:8080"; // Laravel側のURL
axios.defaults.withCredentials = true; // CORSでクッキーを送信

// レスポンスインターセプター
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    // エラーハンドリング（オプション）
    if (error.response?.status === 419) {
      // CSRFトークン切れの場合、再取得
      return axios.get("/sanctum/csrf-cookie").then(() => {
        return axios(error.config);
      });
    }
    return Promise.reject(error);
  }
);

export default axios;
