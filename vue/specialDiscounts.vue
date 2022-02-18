<script>
    import PaginatedTable from '../../components/table/PaginatedTable'
    import SpecialDiscountsApi from '../../services/api/specialDiscountsApi'
    import NotificationMixin from '../../mixin/NotificationMixin'

    export default {
        name: 'specialDiscounts',
        template: require('./templates/SpecialDiscounts.html'),
        components: {'paginated-table': PaginatedTable},
        mixins: [NotificationMixin],
        data: () => {
            return {
                specialDiscountSettings: {
                    tableId: 'specialDiscount',
                    apiUrl: '',
                    total: 0,
                    perPage: 20,
                    resetPage: false,
                    isPageProcessed: false,
                    apiLoading: {create: false, delete: false},
                    isEdit: false
                },
                specialDiscountFields: [
                    {
                        key: 'id',
                        label: 'ID',
                        sortable: true,
                        thClass: 'text-center actions-column',
                        tdClass: 'text-center align-middle'
                    },
                    {
                        key: 'percentage',
                        label: 'Percentage',
                        sortable: true,
                        thClass: 'text-center actions-column',
                        tdClass: 'text-center align-middle'
                    },
                    {
                        key: 'spent',
                        label: 'Spent',
                        sortable: true,
                        thClass: 'text-center actions-column',
                        tdClass: 'text-center align-middle'
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        sortable: true,
                        thClass: 'text-center actions-column',
                        tdClass: 'text-center align-middle'
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        sortable: false,
                        thClass: 'text-center actions-column',
                        tdClass: 'text-center align-middle'
                    }
                ],
                specialDiscountForm: {
                    percentage: 1,
                    status: 1,
                    spent: ''
                },
                editSpecialDiscountForm: {
                    id: 0,
                    percentage: 1,
                    status: 1,
                    spent: ''
                },
                percentages: [
                    {value: 1, text: '1%'},
                    {value: 2, text: '2%'},
                    {value: 3, text: '3%'},
                    {value: 4, text: '4%'},
                    {value: 5, text: '5%'},
                    {value: 6, text: '6%'},
                    {value: 7, text: '7%'},
                    {value: 8, text: '8%'},
                    {value: 9, text: '9%'},
                    {value: 10, text: '10%'},
                    {value: 11, text: '11%'},
                    {value: 12, text: '12%'},
                    {value: 13, text: '13%'},
                    {value: 14, text: '14%'},
                    {value: 15, text: '15%'},
                    {value: 16, text: '16%'},
                    {value: 17, text: '17%'},
                    {value: 18, text: '18%'},
                    {value: 19, text: '19%'},
                    {value: 20, text: '20%'},
                    {value: 21, text: '21%'},
                    {value: 22, text: '22%'},
                    {value: 23, text: '23%'},
                    {value: 24, text: '24%'},
                    {value: 25, text: '25%'},
                ],
                statuses: [
                    {value: 0, text: 'Inactive'},
                    {value: 1, text: 'Active'},
                ],
                statusNames: {
                    0: 'Inactive',
                    1: 'Active'
                }
            }
        },
        mounted () {
            this.specialDiscountSettings.apiUrl = SpecialDiscountsApi.getSpecialDiscountListUrl()
        },
        methods: {
            onAddSpecialDiscount: function (event) {
                event.preventDefault()

                if (!confirm(`Are you sure to create this special discount?`)) {
                    return false
                }

                if (this.specialDiscountForm.percentage == '') {
                    return this.notifyDanger('Enter the percentage')
                }
                if (this.specialDiscountForm.spent == '') {
                    return this.notifyDanger('Enter the spent')
                }

                SpecialDiscountsApi.addSpecialDiscount(Object.assign({}, this.specialDiscountForm)).then(() => {
                    this.notifySuccess('Special discount successfully created')
                    this.refreshTable(true)
                    this.resetAddSpecialDiscountForm()
                })
                    .catch(error => {
                        this.resetAddSpecialDiscountForm()
                        if (typeof (error.response.data) != 'undefined' && typeof (error.response.data.spent) != 'undefined') {
                            return this.notifyDanger(error.response.data.spent[0])
                        }
                        this.notifyDanger(error.message)
                    })
                    .finally(() => {
                        this.specialDiscountSettings.apiLoading.create = false
                    })
            },
            onEditSpecialDiscount: function (event) {
                event.preventDefault()

                if (!confirm(`Are you sure to edit this special discount?`)) {
                    return false
                }

                if (this.editSpecialDiscountForm.percentage == '') {
                    return this.notifyDanger('Enter the percentage')
                }
                if (this.editSpecialDiscountForm.spent == '') {
                    return this.notifyDanger('Enter the spent')
                }

                SpecialDiscountsApi.updateSpecialDiscount(this.editSpecialDiscountForm).then(() => {
                    this.notifySuccess('Special discount successfully updated')
                    this.refreshTable(true)
                    this.cancelEditSpecialDiscount()
                })
                    .catch(error => {
                        this.cancelEditSpecialDiscount();
                        if (typeof (error.response.data) != 'undefined' && typeof (error.response.data.spent) != 'undefined') {
                            return this.notifyDanger(error.response.data.spent[0])
                        }

                        this.notifyDanger(error.message)
                    })
                    .finally(() => {
                        this.specialDiscountSettings.apiLoading.create = false
                    })
            },
            resetAddSpecialDiscountForm: function () {
                this.specialDiscountForm = {
                    percentage: 1,
                    status: 1,
                    spent: ''
                }
            },
            cancelEditSpecialDiscount: function () {
                this.editSpecialDiscountForm = {
                    id: 0,
                    percentage: 1,
                    status: 1,
                    spent: ''
                }
                this.specialDiscountSettings.isEdit = false;
            },
            editSpecialDiscount (spDisId) {
                SpecialDiscountsApi.getSpecialDiscountUrl(spDisId).then((response) => {
                    this.editSpecialDiscountForm.id = response.data.id;
                    this.editSpecialDiscountForm.percentage = response.data.percentage;
                    this.editSpecialDiscountForm.status = response.data.status;
                    this.editSpecialDiscountForm.spent = response.data.spent;
                    this.specialDiscountSettings.isEdit = true;

                    window.scrollTo(0,document.getElementById('specialDiscountsTableContainer').getBoundingClientRect().height)
                })
            },
            onChangePage (page, tableId) {
                this.$emit('page-changed', page)
            },
            onRefreshPage ($event, tableId) {
                this[tableId + 'Settings'].total = $event.totalRows
                this.$emit('page-refreshed', $event.totalRows)
                this[tableId + 'Settings'].isPageProcessed = false
                this[tableId + 'Settings'].resetPage = false
            },
            deleteSpecialDiscount (spDisId) {
                if (!confirm(`Are you sure to delete this special discount?`)) {
                    return false
                }

                SpecialDiscountsApi.deleteSpecialDiscount(spDisId).then(() => {
                    this.notifySuccess('Discount successfully deleted')
                    this.refreshTable(true)
                })
                    .catch(error => {
                        this.notifyDanger(error.message)
                    })
                    .finally(() => {
                        this.resetAddSpecialDiscountForm();
                        if (spDisId === this.editSpecialDiscountForm.id) {
                            this.cancelEditSpecialDiscount();
                        }
                        this.specialDiscountSettings.apiLoading.delete = false
                    })
            },
            refreshTable: function (isResetPage) {
                if (typeof (isResetPage) != 'undefined') {
                    this.specialDiscountSettings.resetPage = true
                }

                this.$refs[this.specialDiscountSettings.tableId].$refs.paginatedTable.refresh()
            }
        }
    }
</script>

<style lang="scss">
    html{
        scroll-behavior: smooth;
    }
    .specialDiscountsTable {
        overflow-x: auto;
    }

    .vue-styles {
        padding-right: 0;
        padding-left: 0;
    }

    .ellipsis {
        max-width: 300px;
    }
    th[aria-sort="none"], th[aria-sort="descending"], th[aria-sort="ascending"]{
        background-position-x: 67% !important;
    }
</style>