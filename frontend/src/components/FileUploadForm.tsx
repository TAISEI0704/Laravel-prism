import { useState, useEffect } from "react";
import type { FormEvent, ChangeEvent } from "react";
import axios from "../lib/axios"; // カスタム設定済みのaxiosインスタンス

const FileUploadForm = () => {
  useEffect(() => {
    // CSRFトークンの取得
    axios.get("/sanctum/csrf-cookie");
  }, []);
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");
  const [date, setDate] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selectedFile = e.target.files[0];
      if (selectedFile.type === "text/plain") {
        setFile(selectedFile);
        setMessage("");
      } else {
        setFile(null);
        setMessage("txtファイルのみアップロード可能です。");
      }
    }
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!file || !title || !date) {
      setMessage("すべての項目を入力してください。");
      return;
    }

    setLoading(true);
    const formData = new FormData();
    formData.append("minutes_txt", file);
    formData.append("title", title);
    formData.append("date", date);

    try {
      const response = await axios.post("/api/minutes", formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });

      if (response.data.minute_id) {
        setMessage(
          `アップロード成功！\n`
        );
      } else {
        setMessage("アップロードは成功しましたが、IDが生成されませんでした。");
      }

      // フォームをリセット
      setFile(null);
      setTitle("");
      setDate("");
    } catch (error) {
      setMessage("アップロード中にエラーが発生しました。");
      console.error("Error:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-md mx-auto p-6 bg-white rounded-lg shadow-lg">
      <h2 className="text-2xl font-bold mb-6">議事録アップロード</h2>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            タイトル
          </label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            会議日
          </label>
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            議事録ファイル (.txt)
          </label>
          <input
            type="file"
            id="minutesTxt"
            accept=".txt"
            onChange={handleFileChange}
            className="mt-1 block w-full"
            required
          />
        </div>

        {message && (
          <div
            className={`text-sm whitespace-pre-line p-3 rounded ${
              message.includes("成功")
                ? "text-green-700 bg-green-50 border border-green-200"
                : "text-red-700 bg-red-50 border border-red-200"
            }`}
          >
            {message}
          </div>
        )}

        <button
          type="submit"
          disabled={loading}
          className={`w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ${
            loading ? "opacity-50 cursor-not-allowed" : ""
          }`}
        >
          {loading ? "アップロード中..." : "アップロード"}
        </button>
      </form>
    </div>
  );
};

export default FileUploadForm;
