import axios from 'axios';

const SpecialDiscountsApi = (function () {
    const apiUrl = '/api/admin/finances/special-discounts/';
    const axiosConfig = {headers: {'X-Requested-With': 'XMLHttpRequest'}};

    /**
     * @return {string}
     */
    const getSpecialDiscountListUrl = () => {
        return `${apiUrl}search`;
    };

    /**
     * @param {int} spDisId
     * @return {Promise<AxiosResponse<T>>}
     */
    const getSpecialDiscountUrl = (spDisId) => {
        return axios.get(`${apiUrl}get/${spDisId}`, axiosConfig);
    };

    /**
     * @param {int} spDisId
     * @return {Promise<AxiosResponse<T>>}
     */
    const deleteSpecialDiscount = (spDisId) => {
        return axios.delete(`${apiUrl}${spDisId}`,  axiosConfig);
    };

    /**
     * @param {object} postData
     * @return {Promise<AxiosResponse<T>>}
     */
    const addSpecialDiscount = postData => {
        return axios.post(`${apiUrl}create`, postData, axiosConfig);
    };

    /**
     * @param {object} postData
     * @return {Promise<AxiosResponse<T>>}
     */
    const updateSpecialDiscount = (postData) => {
        return axios.post(`${apiUrl}update`, postData, axiosConfig);
    };

    return {
        getSpecialDiscountListUrl,
        deleteSpecialDiscount,
        addSpecialDiscount,
        updateSpecialDiscount,
        getSpecialDiscountUrl
    };
})();

export default SpecialDiscountsApi;
